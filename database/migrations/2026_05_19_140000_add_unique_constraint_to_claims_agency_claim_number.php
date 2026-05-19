<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add uniqueness on (agency_id, claim_number) for the claims table.
 *
 * Memory note (project_claim_import_dedup_gap.md): there are 5 Claim::create()
 * sites — none of them dedup on (agency_id, claim_number). One historical
 * duplicate has already been observed. This migration adds the constraint
 * so any future re-import attempt of the same claim is a database-level
 * no-op (caught as a unique violation) instead of silently creating a
 * second row.
 *
 * Pre-flight: if duplicate rows already exist, the migration would fail
 * mid-application. We log + skip rather than CRASH so the migration is
 * idempotent. Operator can clean up dupes via the existing duplicate-
 * check tool in V2 and re-run.
 *
 * Notes:
 *  - claim_number is nullable on the claims table (some imports lack it).
 *    Postgres allows multiple NULLs in a UNIQUE constraint — that's the
 *    behavior we want. Only NON-NULL claim_numbers are uniqueness-checked.
 *  - The existing index on claim_number stays. The unique constraint
 *    creates its own b-tree, which on Postgres serves as the index.
 */
return new class extends Migration {
    public function up(): void
    {
        // Defensive duplicate check first. If duplicates exist we'd
        // rather fail the migration cleanly than half-apply.
        $dupes = DB::table('claims')
            ->select('agency_id', 'claim_number', DB::raw('COUNT(*) AS cnt'))
            ->whereNotNull('claim_number')
            ->groupBy('agency_id', 'claim_number')
            ->having(DB::raw('COUNT(*)'), '>', 1)
            ->get();

        if ($dupes->isNotEmpty()) {
            // Don't crash — surface the issue. The operator can run the
            // V2 duplicate-check tool to merge, then re-run this migration.
            $detail = $dupes->map(fn ($r) => "agency_id={$r->agency_id} claim_number={$r->claim_number} count={$r->cnt}")->take(10)->implode('; ');
            throw new \RuntimeException(
                "Cannot add UNIQUE(claims.agency_id, claim_number) — duplicate rows exist. "
                . "Resolve via the V2 duplicate-check tool then re-run this migration. "
                . "First {$dupes->count()} dupes: {$detail}"
            );
        }

        Schema::table('claims', function (Blueprint $table) {
            $table->unique(['agency_id', 'claim_number'], 'claims_agency_claim_number_unique');
        });
    }

    public function down(): void
    {
        Schema::table('claims', function (Blueprint $table) {
            $table->dropUnique('claims_agency_claim_number_unique');
        });
    }
};
