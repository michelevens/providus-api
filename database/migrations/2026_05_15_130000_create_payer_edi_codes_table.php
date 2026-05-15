<?php

// payer_edi_codes — one row per (Payer, clearinghouse) pairing.
//
// The same insurer can have DIFFERENT EDI payer IDs depending on the
// clearinghouse routing it. UnitedHealthcare is "87726" on Availity
// but a different code on Change Healthcare or Office Ally. Today
// the providus schema mashes one EDI code into payers.stedi_id +
// a parallel-but-empty payers.edi_payer_id — that breaks the moment
// the agency uses two clearinghouses.
//
// This table makes it explicit: each row says "for payer X, when
// you route through clearinghouse Y, use code Z". One row is marked
// is_primary=true per payer so the UI can show a single default
// without us having to encode preference in business logic.
//
// Why not jsonb on payers: queryability. We'll filter "show me all
// payers we have Availity codes for" or "find the payer behind
// EDI code 87726 on Availity" — relational beats jsonb for that.
//
// Migration plan (NOT in this migration — comes next):
//   1. Migrate payers.stedi_id values into rows with clearinghouse='stedi'.
//   2. Drop payers.stedi_id + payers.edi_payer_id columns.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payer_edi_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payer_id')->constrained('payers')->cascadeOnDelete();
            // Lowercase identifier: 'availity', 'stedi', 'change',
            // 'office_ally', 'optum_rev', etc. Free-form string so
            // adding a new clearinghouse doesn't need a migration.
            $table->string('clearinghouse', 50);
            // The EDI payer ID itself. Most are 5-digit numeric
            // (87726, 60054) but some are alpha or longer
            // (CMS Medicare uses "CMS", BCBS plans sometimes have
            // 6-char codes). varchar(50) is safe.
            $table->string('edi_payer_id', 50);
            // Exactly one row per payer carries the "preferred"
            // flag — used by V2 to show "EDI ID: 87726" without
            // listing every clearinghouse on every screen.
            $table->boolean('is_primary')->default(false);
            // Free-form per-pairing context — "production-ready as of
            // 2026-05-15", "test only", "enrollment in progress", etc.
            $table->text('notes')->nullable();
            $table->timestamps();

            // Same payer + clearinghouse can't have two conflicting
            // codes. If a payer changes its code, update the existing
            // row rather than inserting a duplicate.
            $table->unique(['payer_id', 'clearinghouse'], 'payer_edi_codes_unique_pair');
            // Reverse lookup: "what payer is EDI code 87726 on
            // Availity?" — common during ERA import + claim status
            // reconciliation. Composite, not just edi_payer_id alone,
            // because the same code on different clearinghouses can
            // mean different payers.
            $table->index(['clearinghouse', 'edi_payer_id'], 'payer_edi_codes_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payer_edi_codes');
    }
};
