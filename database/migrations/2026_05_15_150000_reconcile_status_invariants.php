<?php

// Status-invariant reconciliation — one-shot data backfill.
//
// Audit found three classes of inconsistent rows on 2026-05-15:
//
//   1A. 14 claims with paid_date set but status=submitted + total_paid=0.
//       Looks like a CSV import mapped a column wrong. Clear paid_date
//       so reports stop showing them as "paid 2025-11-09" when they
//       haven't been paid at all.
//
//   1B. 16 claims with status=denied but no claim_denials row. Half of
//       them have an inline denial_reason text; half are pure orphans.
//       For the ones with denial_reason: create a skeleton claim_denials
//       row capturing the text + a synthetic denial_code so the
//       Denial Inbox + appeal workflow can actually act on them.
//       For the pure orphans: revert status to submitted so they get
//       picked up in the next status-check / re-adjudication pass.
//
//   1C. 31 charge_entries whose status disagrees with their parent
//       claim. Take the claim's status as authoritative and propagate
//       down. (Why claim wins: claims are billed to payers; charges
//       are just the source lines. A claim's final status reflects
//       the payer outcome.)
//
// All three branches are idempotent. Re-running this migration is a
// no-op once the data is clean.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $this->reconcile1A();
            $this->reconcile1B();
            $this->reconcile1C();
        });
    }

    /**
     * 1A: clear bogus paid_date on submitted claims with $0 paid.
     */
    private function reconcile1A(): void
    {
        $rows = DB::table('claims')
            ->whereNull('deleted_at')
            ->whereNotNull('paid_date')
            ->where('status', 'submitted')
            ->where('total_paid', 0)
            ->select('id')
            ->get();

        if ($rows->isEmpty()) {
            fwrite(STDOUT, "  · 1A: no claims to clean (already clean)\n");
            return;
        }

        $ids = $rows->pluck('id')->all();
        DB::table('claims')->whereIn('id', $ids)->update(['paid_date' => null]);
        fwrite(STDOUT, '  · 1A: cleared paid_date on ' . count($ids) . " claims (submitted + total_paid=0)\n");
    }

    /**
     * 1B: reconcile claims marked denied that lack a claim_denials row.
     *   - Has denial_reason text → create skeleton denial row
     *   - No denial_reason       → revert status to submitted
     */
    private function reconcile1B(): void
    {
        $rows = DB::table('claims as c')
            ->whereNull('c.deleted_at')
            ->where('c.status', 'denied')
            ->whereNotExists(function ($q) {
                $q->from('claim_denials')->whereColumn('claim_id', 'c.id');
            })
            ->select('c.id', 'c.agency_id', 'c.denial_reason', 'c.denial_codes', 'c.adjudicated_date', 'c.created_at')
            ->get();

        if ($rows->isEmpty()) {
            fwrite(STDOUT, "  · 1B: no orphan denied claims (already clean)\n");
            return;
        }

        $createdDenials = 0;
        $revertedStatus = 0;
        $now = now();

        foreach ($rows as $r) {
            $hasReason = !empty($r->denial_reason);
            if ($hasReason) {
                // Skeleton denial — captures the inline text so the
                // Denial Inbox can show it. denial_code is required
                // by the workflow; use a synthetic 'BACKFILL' until
                // an operator triages.
                DB::table('claim_denials')->insert([
                    'claim_id'       => $r->id,
                    'agency_id'      => $r->agency_id,
                    'denial_code'    => substr($r->denial_codes ?: 'BACKFILL', 0, 20),
                    'denial_reason'  => $r->denial_reason,
                    'denied_amount'  => 0,
                    'denial_date'    => $r->adjudicated_date ?: $r->created_at,
                    'status'         => 'new',
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ]);
                $createdDenials++;
            } else {
                // Pure orphan — revert to submitted so it shows up
                // again in stale-claim / status-check workflows.
                DB::table('claims')->where('id', $r->id)->update([
                    'status'     => 'submitted',
                    'updated_at' => $now,
                ]);
                $revertedStatus++;
            }
        }

        fwrite(STDOUT, "  · 1B: created {$createdDenials} skeleton denial(s), reverted {$revertedStatus} claim(s) to submitted\n");
    }

    /**
     * 1C: propagate claim.status down to its charge_entries when they
     * disagree. Claim is authoritative.
     */
    private function reconcile1C(): void
    {
        $mismatches = DB::select("
            SELECT ce.id AS charge_id, ce.status AS charge_status, c.status AS claim_status
            FROM charge_entries ce
            JOIN claims c ON c.id = ce.claim_id
            WHERE ce.deleted_at IS NULL
              AND c.deleted_at IS NULL
              AND ce.status != c.status
        ");

        if (empty($mismatches)) {
            fwrite(STDOUT, "  · 1C: no charge/claim status mismatches (already clean)\n");
            return;
        }

        $updated = 0;
        foreach ($mismatches as $m) {
            DB::table('charge_entries')->where('id', $m->charge_id)->update([
                'status'     => $m->claim_status,
                'updated_at' => now(),
            ]);
            $updated++;
        }
        fwrite(STDOUT, "  · 1C: aligned {$updated} charge_entries status to parent claim\n");
    }

    /**
     * Reversible enough — we can't recover the cleared paid_date or
     * the original mismatched charge statuses. Re-creating skeleton
     * denial rows on a re-run is fine (the up() is idempotent).
     */
    public function down(): void
    {
        // Intentional no-op. The forward fix is data cleanup; there's
        // no useful inverse.
    }
};
