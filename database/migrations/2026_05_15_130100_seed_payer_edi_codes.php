<?php

// Backfill payer_edi_codes from the existing payers.stedi_id values
// + add the few known clearinghouse-specific codes operators have
// been working with.
//
// Two sources:
//   1. payers.stedi_id — was populated by PayerSeeder for ~63 catalog
//      payers (UHC=87726, Aetna=60054, Cigna=62308, etc). These are
//      CMS-style payer IDs that also happen to be Stedi's routing
//      IDs. We label them as clearinghouse='stedi' and is_primary=true.
//   2. Hand-known Availity codes for the high-volume payers that
//      were missing from stedi_id. Florida Blue (90% of our claim
//      volume) is the most important — its Availity ID is 00590.
//
// Idempotent: each insert checks for existing (payer_id, clearinghouse)
// pair via DB::table()->updateOrInsert(), so re-running this is safe
// and updates the code if it changed.
//
// The payers.stedi_id and payers.edi_payer_id columns are NOT dropped
// here — that's a follow-up migration once we're confident the new
// table is canonical and all readers have migrated to it.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $now = now();

            // 1. Backfill from payers.stedi_id. This catches the ~63
            //    seeded payers that have a CMS-style code we treat
            //    as both the Stedi and the generic default code.
            $payers = DB::table('payers')
                ->whereNotNull('stedi_id')
                ->where('stedi_id', '!=', '')
                ->select(['id', 'name', 'stedi_id'])
                ->get();

            foreach ($payers as $payer) {
                DB::table('payer_edi_codes')->updateOrInsert(
                    ['payer_id' => $payer->id, 'clearinghouse' => 'stedi'],
                    [
                        'edi_payer_id' => $payer->stedi_id,
                        'is_primary'   => true,
                        'notes'        => 'Backfilled from payers.stedi_id on 2026-05-15.',
                        'updated_at'   => $now,
                        'created_at'   => $now,
                    ],
                );
            }

            // 2. Add known Availity codes for the high-volume payers
            //    that operators have been using through the Availity
            //    portal. These are NOT in stedi_id today.
            //
            //    Code sources: Availity payer search +
            //    https://apps.availity.com/availity/PayerList
            //    (verified by operators during the 2026-04 BCBSNM
            //    appeal sweep).
            $availityCodes = [
                'Florida Blue'                       => '00590',
                'BCBS of New Mexico'                 => 'BCBSNM',
                'BCBS of Texas'                      => 'BCBSTX',
                'UnitedHealthcare'                   => '87726',
                'Aetna'                              => '60054',
                'Cigna'                              => '62308',
                'Humana'                             => '61101',
                // Medicare's Availity ID varies by MAC contractor.
                // First Coast Service Options (FL Medicare) = 09102.
                // We leave 'Medicare' itself unmapped and add
                // contractor-specific codes once we have separate
                // payer rows for each MAC.
            ];

            foreach ($availityCodes as $payerName => $code) {
                $payer = DB::table('payers')->where('name', $payerName)->first();
                if (!$payer) {
                    fwrite(STDOUT, "  · skipped (no payer): {$payerName}\n");
                    continue;
                }
                // The Availity code wins primary over the Stedi code
                // for these specific high-volume payers — operators
                // submit via Availity, so that's the routing reality.
                // First, demote any other primary row for this payer.
                DB::table('payer_edi_codes')
                    ->where('payer_id', $payer->id)
                    ->update(['is_primary' => false, 'updated_at' => $now]);

                DB::table('payer_edi_codes')->updateOrInsert(
                    ['payer_id' => $payer->id, 'clearinghouse' => 'availity'],
                    [
                        'edi_payer_id' => $code,
                        'is_primary'   => true,
                        'notes'        => 'Verified Availity payer ID. Used for EnnHealth claim submission.',
                        'updated_at'   => $now,
                        'created_at'   => $now,
                    ],
                );
            }
        });
    }

    public function down(): void
    {
        // Wipe every row we'd plausibly have created. We can't
        // distinguish "seeded by this migration" from "added later by
        // an operator", so the safe inverse is to delete all rows
        // (since the previous migration creates the empty table).
        DB::table('payer_edi_codes')->truncate();
    }
};
