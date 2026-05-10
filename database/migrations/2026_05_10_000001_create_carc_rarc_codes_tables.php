<?php
// CARC (Claim Adjustment Reason Codes) + RARC (Remittance Advice Remark Codes)
// reference tables. Populated from X12's published lists — the canonical
// source is wpc-edi.com but the codes themselves are stable and rarely change.
//
// Why this matters: when the ERA parser walks CAS segments, it pulls a 2- or
// 3-digit code (e.g. "29", "50", "197"). To turn that into a human-readable
// denial reason ("The time limit for filing has expired") AND a category
// (auto-appealable / coding-fix / eligibility / etc) we need this lookup.
//
// V2's Denial Inbox already consumes denial_category — once this table is
// populated and the parser uses it, V2 stops routing ~70% of denials to
// the "Unknown" queue.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('carc_codes', function (Blueprint $table) {
            $table->string('code', 10)->primary();
            $table->text('description');
            // Maps to V2's Denial Inbox category. One of: auto-appealable,
            // coding-fix, eligibility, authorization, documentation, duplicate, other
            $table->string('category', 30)->default('other');
            $table->string('typical_group_codes', 20)->nullable(); // e.g. "CO,PR" — which CAS group(s) commonly carry this code
            $table->timestamps();

            $table->index('category');
        });

        Schema::create('rarc_codes', function (Blueprint $table) {
            $table->string('code', 10)->primary();
            $table->text('description');
            // Appeal-related RARCs (MA01, MA02, N350, MA130, etc.) need special handling
            $table->boolean('triggers_appeal_window')->default(false);
            $table->boolean('indicates_documentation_request')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rarc_codes');
        Schema::dropIfExists('carc_codes');
    }
};
