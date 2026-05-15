<?php

// Backfill payers.aliases with the spelling variants we know about,
// mirrored from v2/src/lib/payerCanonical.ts (the helper the frontend
// was using as a read-time shim).
//
// The PayerResolver service uses these aliases to map free-text
// payer_name strings to a canonical payer_id. Aliases are lowercase
// substrings — the resolver lowercases the incoming string and
// looks for any alias contained in it. Ordering inside each array
// is informational only (the resolver scans payers in a deterministic
// order; see PayerResolver).
//
// Two extra notes:
//   - Upstream-truncation aliases: we include the truncated form
//     "blue cross blue shield of new" (53 rows in prod) under
//     BCBS New Mexico. Cheaper than fixing the import script
//     retroactively.
//   - Case quirks: production has both "Florida Blue" (1877 rows)
//     and "BLUE CROSS BLUE SHIELD OF FLORIDA" (1392 rows) as
//     separate strings. Both fold to canonical "Florida Blue" via
//     this alias list.
//
// Idempotent: each entry checks for the canonical slug first, skips
// if absent (e.g. Lucet — no Payer row exists yet for it). Re-running
// overwrites the aliases column with the same data.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // canonical-name → aliases. Looked up by Payer.name (exact
        // case-sensitive match — must match what's stored in the
        // payers table on prod). Names verified via SELECT prior to
        // writing this migration.
        $aliasMap = [
            'UnitedHealthcare'      => [
                'unitedhealthcare', 'united healthcare', 'united health',
                // PayerResolver applies longest-alias-first matching so
                // "optum vaccn" (under VA CCN below) wins over "optum"
                // when a claim says "Optum VACCN".
                'uhc', 'optum', 'optum behavioral health', 'optum care',
            ],
            'Aetna'                 => ['aetna'],
            'Cigna'                 => ['cigna', 'evernorth'],
            'Humana'                => ['humana'],
            'Florida Blue'          => [
                'florida blue', 'fl blue', 'bcbs fl', 'bcbsf',
                'blue cross blue shield of florida', 'blue cross blue shield fl',
                'bcbs of florida', 'florida blue (lucet)', 'fl blue (lucet)',
            ],
            // Prod has TWO rows for BCBS NM: id=109 "BCBS of New
            // Mexico" (canonical) + id=119 "Blue Cross Blue Shield
            // of NM" (duplicate). This migration only tags the
            // canonical; the duplicate will be merged in a separate
            // migration patterned after merge_va_ccn_payer_duplicates.
            'BCBS of New Mexico'    => [
                'bcbs nm', 'bcbsnm', 'bcbs of nm', 'bcbs of new mexico',
                'blue cross blue shield of new mexico', 'blue cross new mexico',
                // Upstream-truncated string seen in prod (53 claims).
                'blue cross blue shield of new',
            ],
            'BCBS of Texas'         => [
                'bcbs tx', 'bcbstx', 'bcbs of tx',
                'blue cross blue shield of texas',
            ],
            'Medicare'              => [
                'medicare', 'novitas', 'palmetto', 'first coast',
                'fl medicare', 'medicare of florida first coast',
                'cms',
            ],
            'Tricare'               => ['tricare'],
            'VA CCN (Community Care Network)' => [
                'va community care', 'vaccn', 'optum vaccn', 'va ccn',
                'community care network',
            ],
            'Carelon Behavioral Health' => ['carelon', 'beacon health'],
        ];

        DB::transaction(function () use ($aliasMap) {
            foreach ($aliasMap as $canonicalName => $aliases) {
                $row = DB::table('payers')->where('name', $canonicalName)->first();
                if (!$row) {
                    // Logged via stdout — visible in `artisan migrate`
                    // output on the deploy log. Lucet, for instance,
                    // doesn't have a Payer row yet; that's a separate
                    // task and the resolver will simply not match
                    // those strings until a payer is created.
                    fwrite(STDOUT, "  · skipped (no payer row): {$canonicalName}\n");
                    continue;
                }

                DB::table('payers')
                    ->where('id', $row->id)
                    ->update(['aliases' => json_encode(array_values(array_unique($aliases)))]);
            }
        });
    }

    public function down(): void
    {
        // Wipe aliases on the rows we touched. We can't recover what
        // the column held before (always-NULL — this migration follows
        // the column-creation migration in the same release), so a
        // blanket NULL is the right inverse.
        DB::table('payers')->update(['aliases' => null]);
    }
};
