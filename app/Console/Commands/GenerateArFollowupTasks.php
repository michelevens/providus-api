<?php

namespace App\Console\Commands;

use App\Models\BillingTask;
use App\Models\Claim;
use Illuminate\Console\Command;

/**
 * Daily job: surface aging claims as follow-up tasks for the RCM team.
 *
 * Today, claims with no payer activity past day 30/60/90 just sit in
 * the database. Without a prompt, billers default to working only what
 * they remember — and the slow payers age into timely-filing
 * rejections silently.
 *
 * This command scans every agency's open claims and creates a
 * BillingTask when a claim crosses one of three age cliffs. We don't
 * spam: each (claim_id, age_bucket) pair gets at most one task ever
 * (dedupe is by combination of source = 'ar_aging_<bucket>' +
 * billing_client_id + claim mention in title). When the operator
 * completes the task, no future task fires for the same bucket.
 *
 * Cadence: scheduled daily at 7am ET (12:00 UTC). One run touches
 * every active agency.
 */
class GenerateArFollowupTasks extends Command
{
    protected $signature = 'ar:generate-followup-tasks {--agency= : Limit to one agency for testing}';
    protected $description = 'Create RCM follow-up tasks for open claims aged 30/60/90 days with no movement';

    /** Days threshold → task severity + label. Order matters: we want
     *  to surface the OLDEST cliff a claim has crossed, not all of them. */
    private const BUCKETS = [
        ['threshold' => 90, 'source' => 'ar_aging_90', 'priority' => 'urgent', 'label' => '90+ days'],
        ['threshold' => 60, 'source' => 'ar_aging_60', 'priority' => 'high',   'label' => '60+ days'],
        ['threshold' => 30, 'source' => 'ar_aging_30', 'priority' => 'normal', 'label' => '30+ days'],
    ];

    public function handle(): int
    {
        $agencyFilter = $this->option('agency');
        $now = now();
        $totalCreated = 0;
        $totalSkipped = 0;

        // Open claims = submitted/acknowledged/pending/in_process/partial_paid
        // with a positive balance. We deliberately INCLUDE partial_paid
        // because if a payer paid 60% of a claim and went silent on the
        // remaining 40%, that's exactly the "aging silently" pattern.
        $openStatuses = ['submitted', 'acknowledged', 'pending', 'in_process', 'partial_paid'];

        $claimsQuery = Claim::whereIn('status', $openStatuses)
            ->where('balance', '>', 0)
            ->whereNotNull('submitted_date');

        if ($agencyFilter) {
            $claimsQuery->where('agency_id', $agencyFilter);
        }

        // Stream — agencies with thousands of open claims shouldn't OOM.
        $claimsQuery->chunkById(500, function ($claims) use ($now, &$totalCreated, &$totalSkipped) {
            foreach ($claims as $claim) {
                $ageDays = (int) abs($now->diffInDays($claim->submitted_date));

                // Pick the highest-threshold bucket the claim qualifies for
                $bucket = null;
                foreach (self::BUCKETS as $b) {
                    if ($ageDays >= $b['threshold']) {
                        $bucket = $b;
                        break;
                    }
                }
                if (!$bucket) continue;  // not yet at 30 days

                // Dedup by (agency_id, source, claim_id). BillingTask
                // has dedicated claim_id + source + source_key columns
                // so we lean on those instead of LIKE-searching the
                // description. One task per (claim, bucket) ever.
                $exists = BillingTask::where('agency_id', $claim->agency_id)
                    ->where('source', $bucket['source'])
                    ->where('claim_id', $claim->id)
                    ->exists();

                if ($exists) {
                    $totalSkipped++;
                    continue;
                }

                BillingTask::create([
                    'agency_id' => $claim->agency_id,
                    'billing_client_id' => $claim->billing_client_id,
                    'claim_id' => $claim->id,
                    'title' => "Follow up: {$claim->payer_name} claim aged {$bucket['label']}",
                    'description' => sprintf(
                        "Claim %s for %s · DOS %s · balance $%s · submitted %s (%dd ago). Call payer or run claim status check.",
                        $claim->claim_number ?: "#{$claim->id}",
                        $claim->patient_name ?: 'unknown patient',
                        optional($claim->date_of_service)->format('Y-m-d') ?: '?',
                        number_format((float) $claim->balance, 2),
                        optional($claim->submitted_date)->format('Y-m-d') ?: '?',
                        $ageDays,
                    ),
                    'provider_name' => $claim->rendering_provider_name,
                    'category' => 'claim_followup',
                    'priority' => $bucket['priority'],
                    'status' => 'pending',
                    'due_date' => $now->copy()->addDays(7)->toDateString(),
                    'source' => $bucket['source'],
                    'source_key' => "claim:{$claim->id}:{$bucket['source']}",
                ]);
                $totalCreated++;
            }
        });

        $this->info("ar:generate-followup-tasks complete. Created {$totalCreated}, skipped {$totalSkipped} (already exist).");
        return self::SUCCESS;
    }
}
