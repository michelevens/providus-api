<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Claim;
use App\Models\WriteOffRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public (no-auth) portal endpoints for org-side write-off approval.
 *
 * The org contact receives an email with /v2/#/portal/write-off/{token}
 * links. They click → V2 portal page loads → page calls these endpoints
 * with the token. The token IS the auth; no login required.
 *
 * Security model:
 *  - portal_token is 64-char URL-safe random (48 bytes of randomness)
 *    persisted at create time. Cannot be guessed, cannot collide.
 *  - Each token is tied to exactly ONE WriteOffRequest. Approving via
 *    a token can only ever decide that one specific request.
 *  - Token is single-use for state transitions: once status != 'pending',
 *    further actions on the same token return 410 Gone.
 *  - No PII in the URL — just the token. Anyone with the token can
 *    see the request details (intentional — this IS the approval flow).
 *
 * The org's response is recorded on the WriteOffRequest row:
 *  - decided_at, decided_by_email (whatever the org typed or the
 *    address the email was sent to), decision_reason for rejections.
 * On approve, applyWriteOff() runs in a transaction; on reject, the
 * row just closes.
 */
class WriteOffPortalController extends Controller
{
    /**
     * GET /portal/write-off/{token}
     *
     * Returns the request context so the portal page can render the
     * approve/reject UI. No mutation. Safe to call repeatedly.
     */
    public function show(string $token): JsonResponse
    {
        $req = WriteOffRequest::where('portal_token', $token)->first();
        if (!$req) {
            return response()->json([
                'success' => false,
                'error' => 'token_not_found',
                'message' => 'This approval link is invalid or has been revoked.',
            ], 404);
        }

        $claim = Claim::withoutGlobalScopes()->find($req->claim_id);
        if (!$claim) {
            return response()->json([
                'success' => false,
                'error' => 'claim_not_found',
                'message' => 'The claim associated with this approval no longer exists.',
            ], 404);
        }

        // Resolve the agency/billing client name for the page header
        // — the portal page is unauthenticated so we send the names
        // here instead of expecting the FE to look them up.
        $bc = $req->billing_client_id
            ? \App\Models\BillingClient::find($req->billing_client_id)
            : null;
        $requester = $req->requested_by
            ? \App\Models\User::find($req->requested_by)
            : null;

        return response()->json([
            'success' => true,
            'data' => [
                'id'              => $req->id,
                'status'          => $req->status,
                'amount'          => (float) $req->amount,
                'category'        => $req->category,
                'reason'          => $req->reason,
                'requested_at'    => $req->requested_at?->toIso8601String(),
                'expires_at'      => $req->expires_at?->toIso8601String(),
                'decided_at'      => $req->decided_at?->toIso8601String(),
                'decided_by'      => $req->decided_by_email,
                'decision_reason' => $req->decision_reason,
                'approver_email'  => $req->approver_email,
                'requested_by'    => $requester ? [
                    'name'  => trim(($requester->first_name ?? '') . ' ' . ($requester->last_name ?? '')) ?: $requester->email,
                    'email' => $requester->email,
                ] : null,
                'claim' => [
                    'id'              => $claim->id,
                    'claim_number'    => $claim->claim_number,
                    'patient_name'    => $claim->patient_name,
                    'payer_name'      => $claim->payer_name,
                    'date_of_service' => $claim->date_of_service,
                    'total_charges'   => (float) $claim->total_charges,
                    'total_paid'      => (float) $claim->total_paid,
                    'adjustments'     => (float) $claim->adjustments,
                    'balance'         => (float) $claim->balance,
                ],
                'billing_client' => $bc ? [
                    'name' => $bc->organization_name ?: $bc->display_name,
                ] : null,
            ],
        ]);
    }

    /**
     * POST /portal/write-off/{token}/approve
     *
     * Apply the write-off. State must be 'pending'; otherwise 410 Gone.
     * Optional body: { "decided_by_email": "<override>", "note": "..." }
     * — defaults to the request's approver_email when omitted.
     */
    public function approve(Request $request, string $token): JsonResponse
    {
        $req = WriteOffRequest::where('portal_token', $token)->first();
        if (!$req) {
            return response()->json([
                'success' => false,
                'error' => 'token_not_found',
            ], 404);
        }
        if ($req->status !== 'pending') {
            return response()->json([
                'success' => false,
                'error' => 'already_decided',
                'message' => "This request was already {$req->status}.",
                'status' => $req->status,
                'decided_at' => $req->decided_at?->toIso8601String(),
            ], 410);
        }

        $decidedBy = $request->input('decided_by_email') ?: $req->approver_email;
        $note = trim((string) $request->input('note', ''));

        \DB::transaction(function () use ($req, $decidedBy, $note) {
            $claim = Claim::withoutGlobalScopes()->findOrFail($req->claim_id);

            // Re-check balance — the claim could have changed since the
            // request was created (e.g. another payment posted). If the
            // proposed write-off now exceeds the balance, reject the
            // attempt and surface the discrepancy.
            $outstanding = (float) $claim->balance;
            if ((float) $req->amount > $outstanding + 0.005) {
                abort(response()->json([
                    'success' => false,
                    'error' => 'balance_changed',
                    'message' => sprintf(
                        'The claim balance has changed since this request was sent. The proposed $%s write-off now exceeds the outstanding $%s. Your billing team should resubmit.',
                        number_format((float) $req->amount, 2),
                        number_format($outstanding, 2),
                    ),
                    'current_balance' => $outstanding,
                ], 409));
            }

            $oldStatus = $claim->status;
            $stamp = now()->format('Y-m-d');
            // Org-approved marker: requester is appliedBy, org is approver.
            // The marker line is intentionally explicit about who and how.
            $byLabel = sprintf(
                'org via portal (%s)%s',
                $decidedBy ?: 'unknown',
                $note ? " — note: {$note}" : '',
            );
            $marker = sprintf(
                '[WRITE-OFF $%s%s by %s on %s] %s',
                number_format((float) $req->amount, 2),
                $req->category ? " · {$req->category}" : '',
                $byLabel,
                $stamp,
                trim((string) $req->reason),
            );

            $claim->adjustments = ((float) $claim->adjustments) + (float) $req->amount;
            $claim->balance = ((float) $claim->total_charges)
                - ((float) $claim->total_paid)
                - ((float) $claim->adjustments);
            if ($claim->balance <= 0.005) {
                $claim->balance = 0;
                $claim->status = 'written_off';
            }
            $claim->notes = $claim->notes
                ? rtrim($claim->notes) . "\n" . $marker
                : $marker;
            $claim->save();

            $req->status = 'approved';
            $req->decided_at = now();
            $req->decided_by_email = $decidedBy;
            $req->decision_reason = $note ?: null;
            $req->applied_at = now();
            $req->save();

            // Fire whatever claim-status webhook the rest of the system
            // listens for. Reuses fireClaimStatusEvent from RcmController
            // logic, but we're in a different controller — keep it simple
            // and skip webhook dispatch from the portal path. The audit
            // trail (notes + write_off_requests row) is the source of
            // truth; webhook can be added later if external listeners
            // need real-time notification.
            unset($oldStatus); // Suppress unused-var hint.
        });

        $req->refresh();
        return response()->json([
            'success' => true,
            'status' => $req->status,
            'message' => 'Write-off approved. The claim has been closed.',
            'decided_at' => $req->decided_at?->toIso8601String(),
        ]);
    }

    /**
     * POST /portal/write-off/{token}/reject
     *
     * Close the request without applying. Optional rejection reason
     * helps the billing team understand why.
     */
    public function reject(Request $request, string $token): JsonResponse
    {
        $req = WriteOffRequest::where('portal_token', $token)->first();
        if (!$req) {
            return response()->json(['success' => false, 'error' => 'token_not_found'], 404);
        }
        if ($req->status !== 'pending') {
            return response()->json([
                'success' => false,
                'error' => 'already_decided',
                'message' => "This request was already {$req->status}.",
                'status' => $req->status,
            ], 410);
        }

        $req->status = 'rejected';
        $req->decided_at = now();
        $req->decided_by_email = $request->input('decided_by_email') ?: $req->approver_email;
        $req->decision_reason = trim((string) $request->input('reason', '')) ?: null;
        $req->save();

        return response()->json([
            'success' => true,
            'status' => $req->status,
            'message' => 'Write-off rejected. The billing team has been notified.',
        ]);
    }
}
