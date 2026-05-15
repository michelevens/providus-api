<?php

// Link claims + charge_entries to the canonical Payer record by FK,
// and give the Payer model a place to store alias spellings.
//
// Today both tables only carry payer_name (varchar) — a free-text
// field. Operators and clearinghouses spell the same insurer five
// different ways ("Florida Blue", "BLUE CROSS BLUE SHIELD OF FLORIDA",
// "BCBS of Florida", "BLUE CROSS BLUE SHIELD OF NEW" — that last one
// is upstream-truncated). Every report that groups by payer fragments
// across these spellings, hiding the real consolidation.
//
// The fix is a two-part schema change:
//   1. claims.payer_id  + charge_entries.payer_id  — nullable FKs that
//      a PayerResolver service will stamp from payer_name on insert
//      and on backfill. Nullable because legacy rows pre-backfill and
//      any future "needs resolution" rows live without an FK.
//   2. payers.aliases (jsonb) — list of substring spellings that map
//      to this canonical payer. Seeded from the existing TS
//      PayerCanonical lookup. The resolver reads this column to do
//      the matching; alias maintenance becomes a data task, not a
//      code change.
//   3. payers.needs_review (bool) — set true when the resolver
//      auto-creates a payer because no existing record matched.
//      Surfaces in /payers admin UI as a merge queue.
//
// On DELETE we use nullOnDelete because the canonical payer record
// going away should NOT cascade-delete claims (claims keep payer_name
// as the fallback display source).

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payers', function (Blueprint $table) {
            // Alias spellings that resolve to this payer. Array of
            // lowercase substrings. NULL means "no aliases yet" — the
            // resolver still matches the canonical name itself.
            $table->jsonb('aliases')->nullable()->after('notes');
            // Auto-created from an unrecognized payer_name. Flips to
            // false once an operator merges it into a canonical record
            // or confirms it as its own payer.
            $table->boolean('needs_review')->default(false)->after('aliases');
            $table->index('needs_review', 'payers_needs_review_idx');
        });

        Schema::table('claims', function (Blueprint $table) {
            // After payer_name keeps the related columns adjacent.
            // nullOnDelete: if a canonical payer is deleted (rare —
            // usually they're merged, not deleted) the claim keeps
            // its payer_name string for display.
            $table->foreignId('payer_id')
                ->nullable()
                ->after('payer_name')
                ->constrained('payers')
                ->nullOnDelete();
            $table->index('payer_id', 'claims_payer_id_idx');
        });

        Schema::table('charge_entries', function (Blueprint $table) {
            $table->foreignId('payer_id')
                ->nullable()
                ->after('payer_name')
                ->constrained('payers')
                ->nullOnDelete();
            $table->index('payer_id', 'charge_entries_payer_id_idx');
        });
    }

    public function down(): void
    {
        Schema::table('charge_entries', function (Blueprint $table) {
            $table->dropIndex('charge_entries_payer_id_idx');
            $table->dropForeign(['payer_id']);
            $table->dropColumn('payer_id');
        });

        Schema::table('claims', function (Blueprint $table) {
            $table->dropIndex('claims_payer_id_idx');
            $table->dropForeign(['payer_id']);
            $table->dropColumn('payer_id');
        });

        Schema::table('payers', function (Blueprint $table) {
            $table->dropIndex('payers_needs_review_idx');
            $table->dropColumn(['aliases', 'needs_review']);
        });
    }
};
