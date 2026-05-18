<?php

namespace App\Services;

// AgencyInvoiceGenerator — turns one month of RCM + credentialing
// activity for a billing client into a single invoice with itemized
// line items. Reads the pricing config off the BillingClient row and
// applies the right rates per service:
//   - RCM percentage / per-claim / flat-monthly / hybrid
//   - Credentialing per-app or per-provider-monthly
//   - One-time setup fee (only if not already billed)
//   - Add-on line items: statement sends, eligibility checks, denial appeals
//
// Returns an array describing the invoice; the caller persists it via
// Invoice::create() + InvoiceItem::create() so this service stays
// side-effect-free for previews ("what would last month have been?").

use App\Models\BillingClient;
use App\Models\Claim;
use App\Models\Application;
use App\Models\Provider;
use Carbon\Carbon;

class AgencyInvoiceGenerator
{
    /**
     * Compute the invoice payload for a billing client + period.
     *
     * @param BillingClient $bc
     * @param Carbon $periodStart  Inclusive (e.g. first of month)
     * @param Carbon $periodEnd    Inclusive (e.g. last of month)
     * @return array{
     *   billing_client: BillingClient,
     *   period_start: string,
     *   period_end: string,
     *   line_items: array<int, array{description: string, quantity: float, unit_price: float, total: float}>,
     *   subtotal: float,
     *   activity: array{paid: float, billed: float, adjudicated: float, claim_count: int, app_count: int, provider_count: int}
     * }
     */
    public function preview(BillingClient $bc, Carbon $periodStart, Carbon $periodEnd): array
    {
        $items = [];

        // ── Setup fee (one-time, gated on the billed flag) ────────────
        $setupFee = (float) $bc->setup_fee;
        if ($setupFee > 0 && !$bc->setup_fee_billed) {
            $items[] = [
                'description' => 'One-time setup fee',
                'quantity' => 1,
                'unit_price' => $setupFee,
                'total' => $setupFee,
            ];
        }

        // ── Aggregate activity for the period ─────────────────────────
        // Claims with paid_date in [start,end] — drives % of collections.
        // Joins against claim_payments would catch checks/EFTs more
        // accurately, but for v1 we use claim-level paid_amount sums.
        $paidClaims = Claim::where('agency_id', $bc->agency_id)
            ->where('billing_client_id', $bc->id)
            ->whereBetween('paid_date', [$periodStart, $periodEnd])
            ->get(['id', 'total_paid', 'total_charges', 'total_allowed']);

        $collections = (float) $paidClaims->sum('total_paid');
        $billed      = (float) $paidClaims->sum('total_charges');
        $adjudicated = (float) $paidClaims->sum('total_allowed');

        // Claims SUBMITTED in period — drives per-claim and per-app counts.
        $submittedCount = Claim::where('agency_id', $bc->agency_id)
            ->where('billing_client_id', $bc->id)
            ->whereBetween('submitted_date', [$periodStart, $periodEnd])
            ->count();

        // ── RCM line ───────────────────────────────────────────────────
        switch ($bc->rcm_pricing_model) {
            case 'percentage':
                $rate = (float) $bc->rcm_percentage_rate;
                $basis = $bc->rcm_percentage_basis;
                $base = match ($basis) {
                    'billed' => $billed,
                    'adjudicated' => $adjudicated,
                    default => $collections,
                };
                if ($rate > 0 && $base > 0) {
                    $pct = round($rate * 100, 2);
                    $amount = round($base * $rate, 2);
                    $items[] = [
                        'description' => sprintf('RCM service fee — %s%% of %s ($%s)', $pct, $basis, number_format($base, 2)),
                        'quantity' => 1,
                        'unit_price' => $amount,
                        'total' => $amount,
                    ];
                }
                break;

            case 'per_claim':
                $perClaim = (float) $bc->rcm_per_claim_rate;
                if ($perClaim > 0 && $submittedCount > 0) {
                    $items[] = [
                        'description' => sprintf('Claims submitted (%d × $%s)', $submittedCount, number_format($perClaim, 2)),
                        'quantity' => $submittedCount,
                        'unit_price' => $perClaim,
                        'total' => round($submittedCount * $perClaim, 2),
                    ];
                }
                break;

            case 'flat_monthly':
                $base = (float) $bc->rcm_monthly_base;
                if ($base > 0) {
                    $items[] = [
                        'description' => 'RCM service — monthly retainer',
                        'quantity' => 1,
                        'unit_price' => $base,
                        'total' => $base,
                    ];
                }
                break;

            case 'hybrid':
                // Retainer + percentage of collections above 0.
                $base = (float) $bc->rcm_monthly_base;
                if ($base > 0) {
                    $items[] = [
                        'description' => 'RCM service — monthly retainer',
                        'quantity' => 1,
                        'unit_price' => $base,
                        'total' => $base,
                    ];
                }
                $rate = (float) $bc->rcm_percentage_rate;
                if ($rate > 0 && $collections > 0) {
                    $pct = round($rate * 100, 2);
                    $amount = round($collections * $rate, 2);
                    $items[] = [
                        'description' => sprintf('RCM service — %s%% of collections ($%s)', $pct, number_format($collections, 2)),
                        'quantity' => 1,
                        'unit_price' => $amount,
                        'total' => $amount,
                    ];
                }
                break;

            case 'none':
            default:
                // No RCM line.
                break;
        }

        // ── Credentialing line ────────────────────────────────────────
        $apps = Application::where('agency_id', $bc->agency_id)
            ->whereHas('organization', fn ($q) => $q->where('id', $bc->organization_id))
            ->whereBetween('submitted_date', [$periodStart, $periodEnd])
            ->count();

        // For per-provider monthly, we count active providers in the org
        // (regardless of activity in the period). Default to 0 when the
        // org isn't linked.
        $activeProviders = $bc->organization_id
            ? Provider::where('agency_id', $bc->agency_id)
                ->where('organization_id', $bc->organization_id)
                ->where('is_active', true)
                ->count()
            : 0;

        switch ($bc->credentialing_pricing_model) {
            case 'per_app':
                $perApp = (float) $bc->credentialing_per_app_rate;
                if ($perApp > 0 && $apps > 0) {
                    $items[] = [
                        'description' => sprintf('Credentialing applications submitted (%d × $%s)', $apps, number_format($perApp, 2)),
                        'quantity' => $apps,
                        'unit_price' => $perApp,
                        'total' => round($apps * $perApp, 2),
                    ];
                }
                break;

            case 'per_provider_monthly':
                $perProv = (float) $bc->credentialing_per_provider_rate;
                if ($perProv > 0 && $activeProviders > 0) {
                    $items[] = [
                        'description' => sprintf('Credentialing maintenance (%d providers × $%s)', $activeProviders, number_format($perProv, 2)),
                        'quantity' => $activeProviders,
                        'unit_price' => $perProv,
                        'total' => round($activeProviders * $perProv, 2),
                    ];
                }
                break;

            case 'included':
            case 'none':
            default:
                // No credentialing line.
                break;
        }

        // ── Add-on activity lines ─────────────────────────────────────
        // Statement sends — count last_sent_date in period across the
        // org's statements. patient_statements has a billing_client_id FK.
        if ((float) $bc->statement_send_rate > 0) {
            $statementSends = \DB::table('patient_statements')
                ->where('agency_id', $bc->agency_id)
                ->where('billing_client_id', $bc->id)
                ->whereBetween('last_sent_date', [$periodStart, $periodEnd])
                ->count();
            if ($statementSends > 0) {
                $rate = (float) $bc->statement_send_rate;
                $items[] = [
                    'description' => sprintf('Patient statements sent (%d × $%s)', $statementSends, number_format($rate, 2)),
                    'quantity' => $statementSends,
                    'unit_price' => $rate,
                    'total' => round($statementSends * $rate, 2),
                ];
            }
        }

        // Eligibility checks. The eligibility_checks table doesn't carry
        // billing_client_id today, so attribution is best-effort by
        // agency only — over-counts when an agency has >1 billing client.
        // Acceptable for v1 because rate defaults to 0; tighten when we
        // add a billing_client_id column to the table.
        if ((float) $bc->eligibility_check_rate > 0) {
            $eligChecks = \DB::table('eligibility_checks')
                ->where('agency_id', $bc->agency_id)
                ->whereBetween('created_at', [$periodStart, $periodEnd])
                ->count();
            if ($eligChecks > 0) {
                $rate = (float) $bc->eligibility_check_rate;
                $items[] = [
                    'description' => sprintf('Eligibility checks (%d × $%s)', $eligChecks, number_format($rate, 2)),
                    'quantity' => $eligChecks,
                    'unit_price' => $rate,
                    'total' => round($eligChecks * $rate, 2),
                ];
            }
        }

        // Denial appeals submitted in period
        if ((float) $bc->denial_appeal_rate > 0) {
            $appeals = \DB::table('claim_denials')
                ->where('agency_id', $bc->agency_id)
                ->where('billing_client_id', $bc->id)
                ->whereBetween('appeal_submitted_date', [$periodStart, $periodEnd])
                ->count();
            if ($appeals > 0) {
                $rate = (float) $bc->denial_appeal_rate;
                $items[] = [
                    'description' => sprintf('Denial appeals filed (%d × $%s)', $appeals, number_format($rate, 2)),
                    'quantity' => $appeals,
                    'unit_price' => $rate,
                    'total' => round($appeals * $rate, 2),
                ];
            }
        }

        $subtotal = array_sum(array_column($items, 'total'));

        return [
            'billing_client' => $bc,
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodEnd->toDateString(),
            'line_items' => $items,
            'subtotal' => round($subtotal, 2),
            'activity' => [
                'paid' => $collections,
                'billed' => $billed,
                'adjudicated' => $adjudicated,
                'claim_count' => $submittedCount,
                'app_count' => $apps,
                'provider_count' => $activeProviders,
            ],
        ];
    }
}
