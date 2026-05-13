<?php

// Medicare Physician Fee Schedule rates — reference data shared by
// every agency. Today this is the source of truth for the
// "vs Medicare" benchmark on the PayerDetail CPT analysis tab and
// (eventually) the FeeCalculator / UnderpaymentDetector pages, which
// currently hardcode their baseline rates in JS arrays.
//
// Why a dedicated table instead of leaning on fee_schedules?
//   - fee_schedules is per-agency contract data; Medicare rates are
//     the same for everyone in a given (state, year). Putting them
//     in fee_schedules forced each agency to re-import the schedule.
//   - We want a clean (cpt, state, year) lookup with both
//     non-facility + facility rates. fee_schedules has a single
//     contracted_rate column and no facility split.
//   - Source provenance matters (cms-import vs manual). Easier in a
//     dedicated table.
//
// Locality model: keep it simple — state-level rates. Real MPFS has
// MAC + locality codes (e.g., FL has 03102, 03104). For behavioral
// health most localities within a state collapse to similar rates,
// so a single (state, cpt, year) lookup is the practical-enough
// resolution. Later we can refine by adding locality_code when we
// load multi-locality data.
//
// Modifier model: most psych codes have no modifier impact, but
// 95 (telehealth) and AH/HO/HN (provider-license type) DO affect
// reimbursement. Stored alongside cpt + modifier so the eventual
// "claim with modifier 95" lookup gets the telehealth-adjusted rate.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('medicare_rates', function (Blueprint $table) {
            $table->id();
            $table->string('cpt_code', 10);
            $table->string('modifier', 4)->nullable();
            $table->string('cpt_description')->nullable();
            // 2-char USPS code. NULL = national average (used as
            // fallback when a state-specific rate isn't on file).
            $table->string('state', 2)->nullable();
            // CMS MAC + locality code (e.g., '03102'). Optional.
            $table->string('locality_code', 8)->nullable();
            $table->integer('year');
            // Most pages want non-facility (office). facility_rate is
            // for POS 21/22/23 etc. and is generally lower.
            $table->decimal('non_facility_rate', 10, 2)->nullable();
            $table->decimal('facility_rate', 10, 2)->nullable();
            $table->date('effective_date')->nullable();
            $table->string('source', 32)->default('manual'); // manual | cms_csv | api
            $table->timestamps();

            // Composite unique so reloading the same CSV is idempotent.
            // Treats NULL-state/NULL-modifier/NULL-locality as distinct
            // by Postgres semantics — we collapse with COALESCE in the
            // application-layer upsert if we ever need true dedupe
            // across nulls.
            $table->unique(['cpt_code', 'modifier', 'state', 'locality_code', 'year', 'effective_date'], 'mpfs_unique');

            $table->index(['cpt_code', 'state', 'year']);
            $table->index(['state', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medicare_rates');
    }
};
