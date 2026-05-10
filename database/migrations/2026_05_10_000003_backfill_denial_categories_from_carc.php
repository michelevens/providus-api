<?php
// One-shot backfill: re-categorize existing claim_denials rows by joining
// to the new carc_codes table. Only touches rows where denial_code IS NOT
// NULL (so denial_code='29' becomes denial_category='auto-appealable', etc).
//
// For rows with NULL denial_code (the 38 denials in the EnnHealth DB as of
// 2026-05-10 — backend's old parser never populated the code), this migration
// can't help. To fix those, re-import the original 835 files through the new
// /rcm/era/upload endpoint and the Era835Importer will create fresh denial
// rows with codes attached.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Join claim_denials → carc_codes by denial_code to populate denial_category
        // from the canonical X12 mapping. Use a CASE expression as fallback for
        // rows whose code didn't make it into the seeded list.
        DB::statement("
            UPDATE claim_denials
            SET denial_category = COALESCE(
                (SELECT category FROM carc_codes WHERE carc_codes.code = claim_denials.denial_code LIMIT 1),
                CASE
                    WHEN denial_code IN ('29','50','22','45') THEN 'auto-appealable'
                    WHEN denial_code IN ('4','11','16','181','182','125','146') THEN 'coding-fix'
                    WHEN denial_code IN ('27','31','26') THEN 'eligibility'
                    WHEN denial_code IN ('197','198','15','39','170','171') THEN 'authorization'
                    WHEN denial_code = '18' THEN 'duplicate'
                    WHEN denial_code IN ('208','252','256') THEN 'documentation'
                    ELSE denial_category
                END
            )
            WHERE denial_code IS NOT NULL AND denial_code != ''
        ");

        // Also populate denial_reason from the carc_codes table when the existing
        // reason is empty / generic. This makes the Denial Inbox letter generator
        // produce better drafts.
        DB::statement("
            UPDATE claim_denials cd
            SET denial_reason = c.description
            FROM carc_codes c
            WHERE c.code = cd.denial_code
              AND (cd.denial_reason IS NULL
                   OR cd.denial_reason = ''
                   OR cd.denial_reason = 'Denied per payer remittance')
        ");
    }

    public function down(): void
    {
        // No-op — this is a data fix, not a schema change. Reverting would
        // require snapshotting prior values, which we don't.
    }
};
