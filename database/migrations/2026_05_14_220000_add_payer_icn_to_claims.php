<?php

// Surface the payer's claim control number ("ICN") on the claim row.
//
// The submitter (us / Tebra) generates claim_number on the way out.
// The payer assigns its OWN identifier when the 837 lands — the ICN.
// Remits and EOBs prominently display the ICN as "Claim #", which is
// what operators see when they look at Availity or call the payer.
// Without it stored, an operator pasting the Availity claim # into
// Credentik search gets zero results even though the claim exists.
//
// Why two columns:
//   - payer_icn: the 17-char ICN from CLP07 in the 835. The one shown
//     on remits and what operators paste. Indexed because it'll be
//     in the claim search WHERE clause.
//   - payer_claim_control_number: 837 segment 2300/REF*F8 — the
//     original claim number when submitting a CORRECTED or REPLACEMENT
//     claim. Different concept, but the column belongs with ICN
//     conceptually. Not indexed (rare lookups).

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('claims', function (Blueprint $table) {
            $table->string('payer_icn', 40)->nullable()->after('claim_number');
            $table->string('payer_claim_control_number', 40)->nullable()->after('payer_icn');
            $table->index('payer_icn', 'claims_payer_icn_idx');
        });
    }

    public function down(): void
    {
        Schema::table('claims', function (Blueprint $table) {
            $table->dropIndex('claims_payer_icn_idx');
            $table->dropColumn(['payer_icn', 'payer_claim_control_number']);
        });
    }
};
