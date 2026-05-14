<?php

namespace App\Support;

use App\Models\BillingClient;
use App\Models\Claim;
use App\Models\User;

/**
 * Write-off approval policy resolver.
 *
 * Replaces the old single-threshold $500 gate with a per-billing-client
 * policy. The ORG (the practice whose money it is) owns the policy;
 * the agency configures it on their behalf only when the org hasn't
 * set anything. Either party can write it.
 *
 * Returns a Decision describing what should happen with the write-off:
 *
 *   auto_approve     — applies immediately. Best path; happens when the
 *                      policy explicitly allows the category+amount, or
 *                      when amount falls below the per-category cap.
 *   org_required     — pause; create a WriteOffRequest, email the org's
 *                      contact email, wait for them to click approve/reject
 *                      on a signed-token portal URL.
 *   owner_required   — pause; create a billing_tasks entry for the agency
 *                      owner queue. Used as the fallback path when org
 *                      doesn't respond within the configured window.
 *   denied           — policy says NO under any condition. Rare; would
 *                      come up if the org configured "never write off"
 *                      for a category.
 *
 * The platform default (when no per-org policy is set) is conservative:
 * auto-approve only contractual adjustments + small_balance under $25,
 * org-approval for everything else regardless of amount, owner fallback
 * after 5 days. This is the same shape an org can override.
 */
class WriteOffApproval
{
    public const DECISION_AUTO = 'auto_approve';
    public const DECISION_ORG = 'org_required';
    public const DECISION_OWNER = 'owner_required';
    public const DECISION_DENIED = 'denied';

    /** Platform default policy, used when billing_client.writeoff_policy_json is null. */
    public static function defaultPolicy(): array
    {
        return [
            'auto_approve' => [
                'categories' => ['contractual'],
                'max_amount_per_category' => [
                    'small_balance' => 25.00,
                    'contractual'   => null,
                ],
                'max_amount_overall' => null,
            ],
            'requires_org_approval' => [
                'categories' => ['charity', 'bad_debt', 'timely_filing', 'admin_error', 'other'],
                'min_amount' => 0,
                'contact_email' => null,
            ],
            'fallback_to_owner_after_days' => 5,
            'configured_by' => 'platform_default',
            'configured_at' => null,
        ];
    }

    /**
     * Resolve the policy for this claim. Returns the merged policy
     * (org's policy if set, otherwise platform default).
     */
    public static function policyForClaim(Claim $claim): array
    {
        $bcId = $claim->billing_client_id ?? null;
        if (!$bcId) {
            return self::defaultPolicy();
        }
        $bc = BillingClient::find($bcId);
        if (!$bc || empty($bc->writeoff_policy_json)) {
            return self::defaultPolicy();
        }
        // Persisted policy is jsonb; Laravel returns it as array.
        $policy = $bc->writeoff_policy_json;
        if (!is_array($policy)) {
            // Defensive — if someone wrote it as a string somehow.
            $decoded = json_decode((string) $policy, true);
            $policy = is_array($decoded) ? $decoded : [];
        }
        // Shallow-merge over defaults so missing keys get sensible values.
        return array_replace_recursive(self::defaultPolicy(), $policy);
    }

    /**
     * Resolve the decision for a proposed write-off. The caller
     * (writeOffClaim controller) then dispatches based on the decision.
     *
     * $superadminBypass: superadmin users can apply any write-off
     * directly. Useful for support tickets and incident recovery. Not
     * exposed in the UI; only callable via API or tinker.
     */
    public static function decide(Claim $claim, float $amount, ?string $category, User $user): array
    {
        // Superadmin always bypasses. The audit log + claim notes
        // marker still record who did it.
        if ($user->role === 'superadmin') {
            return [
                'decision' => self::DECISION_AUTO,
                'reason' => 'superadmin bypass',
            ];
        }

        $policy = self::policyForClaim($claim);
        $cat = (string) ($category ?? '');

        // Auto-approve rules
        $auto = $policy['auto_approve'] ?? [];
        $autoCategories = $auto['categories'] ?? [];
        $maxPerCategory = $auto['max_amount_per_category'] ?? [];
        $maxOverall = $auto['max_amount_overall'] ?? null;

        // Category is whitelisted with no per-category cap.
        if (in_array($cat, $autoCategories, true)) {
            $cap = $maxPerCategory[$cat] ?? null;
            if ($cap === null || $amount <= (float) $cap + 0.005) {
                if ($maxOverall === null || $amount <= (float) $maxOverall + 0.005) {
                    return [
                        'decision' => self::DECISION_AUTO,
                        'reason' => sprintf('category=%s auto-approved under policy', $cat),
                    ];
                }
            }
        }
        // Category isn't whitelisted but is under the per-category cap
        // (e.g. small_balance under $25 even though the category itself
        // isn't in the always-auto list).
        if (isset($maxPerCategory[$cat])) {
            $cap = $maxPerCategory[$cat];
            if ($cap !== null && $amount <= (float) $cap + 0.005) {
                if ($maxOverall === null || $amount <= (float) $maxOverall + 0.005) {
                    return [
                        'decision' => self::DECISION_AUTO,
                        'reason' => sprintf('category=%s under $%s cap auto-approved', $cat, number_format((float) $cap, 2)),
                    ];
                }
            }
        }

        // Org-approval rules
        $org = $policy['requires_org_approval'] ?? [];
        $orgCategories = $org['categories'] ?? [];
        $orgMin = (float) ($org['min_amount'] ?? 0);

        // If the category is explicitly in the org list (or empty list +
        // amount exceeds min) and we have a contact email, send to org.
        $catInOrgList = empty($orgCategories) || in_array($cat, $orgCategories, true);
        if ($catInOrgList && $amount >= $orgMin) {
            $contactEmail = self::resolveOrgEmail($claim, $policy);
            if ($contactEmail) {
                return [
                    'decision' => self::DECISION_ORG,
                    'reason' => sprintf('category=%s requires org approval', $cat),
                    'contact_email' => $contactEmail,
                    'fallback_after_days' => $policy['fallback_to_owner_after_days'] ?? null,
                ];
            }
            // No org contact email on file — fall through to owner.
        }

        // Default fallthrough: owner approval. This catches policy gaps
        // (e.g. category not in any list) so write-offs never silently
        // auto-approve when they don't match any rule.
        return [
            'decision' => self::DECISION_OWNER,
            'reason' => 'no policy rule matched; defaulting to owner approval',
        ];
    }

    // ───────────────────────────────────────────────────────────────
    //  Legacy compatibility shims
    // ───────────────────────────────────────────────────────────────
    // Eight callsites in RcmController + RcmPhase2Controller still call
    // the old canApprove() / rejectionMessage() / THRESHOLD_USD API.
    // Rather than do a big-bang refactor and risk breaking all of them,
    // the shims keep the old behavior intact while the new policy-based
    // path lives alongside on the dedicated writeOffClaim endpoint.
    //
    // The shims encode the OLD platform behavior: anyone in
    // ['agency','owner','superadmin'] could auto-approve up to any
    // amount; everyone else was capped at $500. Callsites that need
    // the new per-org policy should call ::decide() directly.

    public const THRESHOLD_USD = 500.0;

    private const APPROVER_ROLES = ['agency', 'owner', 'superadmin'];

    /** Legacy yes/no gate. Use ::decide() for new code. */
    public static function canApprove(User $user, float $amount): bool
    {
        if ($amount <= self::THRESHOLD_USD) return true;
        return in_array($user->role, self::APPROVER_ROLES, true);
    }

    /** Legacy 403 message builder. */
    public static function rejectionMessage(float $amount): string
    {
        return sprintf(
            'Write-offs over $%s require agency-owner approval. This amount is $%s. Ask an owner to complete it.',
            number_format(self::THRESHOLD_USD, 0),
            number_format($amount, 2),
        );
    }

    /** Returns the email address the org-approval request should go to. */
    private static function resolveOrgEmail(Claim $claim, array $policy): ?string
    {
        // Explicit override in the policy wins.
        $override = $policy['requires_org_approval']['contact_email'] ?? null;
        if (is_string($override) && filter_var($override, FILTER_VALIDATE_EMAIL)) {
            return $override;
        }
        // Otherwise pull from the billing_client's contact_email.
        $bcId = $claim->billing_client_id ?? null;
        if (!$bcId) return null;
        $bc = BillingClient::find($bcId);
        $email = $bc->contact_email ?? null;
        return ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) ? $email : null;
    }
}
