<?php

// VA CCN payer dedupe.
//
// Two payer rows existed for the same real-world payer:
//   - id=74  "VA CCN (Community Care Network)"  ← canonical
//   - id=76  "Optum VA CCN"                     ← duplicate, drop
//
// Plus 6 applications had free-text payer_name = "VACCN" with a NULL
// payer_id (operators typed the name without picking from the catalog).
//
// This migration:
//   1. Backfills the 6 orphan applications: payer_id = 74,
//      payer_name = "VA CCN (Community Care Network)".
//   2. Reassigns any application/payer_rules row that points at id=76
//      onto id=74 (currently 0 rows but the SQL is cheap insurance in
//      case prod state drifts before this runs).
//   3. Deletes payers id=76.
//
// The seeder change in this same PR removes the "Optum VA CCN" entry
// so re-seeding won't bring it back. The frontend dedupe (PayersPage)
// makes any future name drift cosmetic instead of data-fragmenting.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            // If id=74 isn't present we treat this migration as a no-op.
            // Same if id=76 was already cleaned by hand. Idempotent.
            $canonical = DB::table('payers')->where('id', 74)->first();
            if (!$canonical) {
                return;
            }

            // 1. Backfill VACCN orphans → link to id=74, standardize name.
            DB::table('applications')
                ->whereNull('payer_id')
                ->where(function ($q) {
                    $q->whereRaw('LOWER(payer_name) LIKE ?', ['%vaccn%'])
                      ->orWhereRaw('LOWER(payer_name) LIKE ?', ['%va ccn%']);
                })
                ->update([
                    'payer_id' => 74,
                    'payer_name' => 'VA CCN (Community Care Network)',
                    'updated_at' => now(),
                ]);

            // 2. Reassign anything pointing at the duplicate.
            DB::table('applications')
                ->where('payer_id', 76)
                ->update([
                    'payer_id' => 74,
                    'payer_name' => 'VA CCN (Community Care Network)',
                    'updated_at' => now(),
                ]);

            // payer_rules is keyed by payer_name (no FK), so just
            // rewrite the string. Optum VA CCN → canonical name.
            DB::table('payer_rules')
                ->whereRaw('LOWER(payer_name) LIKE ?', ['%optum va ccn%'])
                ->orWhereRaw('LOWER(payer_name) = ?', ['vaccn'])
                ->update([
                    'payer_name' => 'VA CCN (Community Care Network)',
                    'updated_at' => now(),
                ]);

            // claims.payer_name is free-text. Normalize any stray
            // "VACCN" / "Optum VA CCN" / "Optum VACCN" spellings so
            // downstream reporting stops splitting the bucket.
            DB::table('claims')
                ->where(function ($q) {
                    $q->whereRaw('LOWER(payer_name) = ?', ['vaccn'])
                      ->orWhereRaw('LOWER(payer_name) LIKE ?', ['%optum va ccn%'])
                      ->orWhereRaw('LOWER(payer_name) LIKE ?', ['%optum vaccn%']);
                })
                ->update([
                    'payer_name' => 'VA CCN (Community Care Network)',
                    'updated_at' => now(),
                ]);

            // 3. Drop the duplicate payer row.
            DB::table('payers')->where('id', 76)->delete();
        });
    }

    public function down(): void
    {
        // No reverse. The "Optum VA CCN" row was a duplicate that the
        // SupplementalPayerSeeder still describes — re-seeding the
        // catalog from scratch (post-seeder-fix) is the right rollback.
        // Backfilled applications stay linked to id=74; reverting that
        // would orphan them again.
    }
};
