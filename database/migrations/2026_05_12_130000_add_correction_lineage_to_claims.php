<?php

// Correction lineage on claims.
//
// When a denial gets a coding fix (missing modifier, wrong dx pointer,
// taxonomy mismatch), the biller creates a NEW claim. Today that new
// claim is unrelated to the original — no link, no audit, no way to
// trend "we keep coding 99214 wrong for BCBS." Both fields are
// optional + nullable so existing claims aren't affected.
//
// original_claim_id        — the claim this one corrects
// corrected_from_denial_id — the specific denial that triggered the
//                            correction (audit + trending)
//
// Future: a HasMany('corrections') on Claim would surface "how many
// times has this same claim been re-billed?" — useful for detecting
// payer responses that aren't really new (recoupments, partial pays).

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('claims', function (Blueprint $table) {
            $table->foreignId('original_claim_id')->nullable()->after('id')->constrained('claims')->nullOnDelete();
            $table->foreignId('corrected_from_denial_id')->nullable()->after('original_claim_id')->constrained('claim_denials')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('claims', function (Blueprint $table) {
            $table->dropConstrainedForeignId('original_claim_id');
            $table->dropConstrainedForeignId('corrected_from_denial_id');
        });
    }
};
