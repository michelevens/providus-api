<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // US States & Territories
        Schema::create('states', function (Blueprint $table) {
            $table->id();
            $table->string('code', 2)->unique();
            $table->string('name', 50);
            $table->string('region', 20);          // northeast, southeast, midwest, south, west, pacific_nw
            $table->integer('population')->nullable(); // thousands
            $table->boolean('is_compact_nlc')->default(false);  // Nurse Licensure Compact
            $table->boolean('is_compact_psypact')->default(false); // Psychology Interjurisdictional Compact
            $table->boolean('is_compact_aslp')->default(false);   // Audiology & SLP Compact
            $table->boolean('is_compact_pt')->default(false);     // Physical Therapy Compact
            $table->boolean('is_compact_ot')->default(false);     // Occupational Therapy Compact
            $table->boolean('is_compact_counseling')->default(false); // Counseling Compact
        });

        // Document Types for credentialing
        Schema::create('document_types', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 50)->unique();
            $table->string('name', 100);
            $table->string('category', 30);        // identity, education, license, insurance, employment, compliance, facility, other
            $table->text('description')->nullable();
            $table->boolean('is_required')->default(false);       // required for most payers
            $table->boolean('has_expiration')->default(false);     // needs renewal tracking
            $table->integer('typical_validity_months')->nullable(); // how long before renewal
            $table->integer('sort_order')->default(0);
        });

        // CPT Procedure Codes
        Schema::create('cpt_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('short_description', 100);
            $table->text('description')->nullable();
            $table->string('category', 50);         // evaluation, psychotherapy, em_visit, preventive, surgical, radiology, lab, medicine, etc.
            $table->string('specialty_group', 50)->nullable(); // behavioral_health, primary_care, surgery, cardiology, etc.
            $table->decimal('avg_medicare_rate', 8, 2)->nullable(); // national avg Medicare reimbursement
            $table->string('time_unit', 10)->nullable();  // minutes, units, per_diem
            $table->integer('typical_minutes')->nullable();
            $table->boolean('telehealth_eligible')->default(false);
            $table->boolean('is_active')->default(true);
        });

        // Place of Service Codes
        Schema::create('place_of_service_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 5)->unique();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->boolean('is_facility')->default(false);
            $table->boolean('is_active')->default(true);
        });

        // Billing Modifiers
        Schema::create('billing_modifiers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 5)->unique();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->string('category', 30);         // telehealth, supervision, level_of_service, facility, anesthesia, procedure, other
            $table->boolean('is_active')->default(true);
        });

        // License Types (reference catalog)
        Schema::create('license_types', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 30)->unique();
            $table->string('abbreviation', 20);
            $table->string('name', 100);
            $table->string('discipline', 50);        // physician, nursing, psychology, social_work, counseling, therapy, pharmacy, dental, allied_health
            $table->string('education_requirement', 50)->nullable(); // MD, DO, DNP, MSN, PhD, PsyD, MSW, MA, BS, etc.
            $table->boolean('requires_supervision')->default(false);
            $table->boolean('can_prescribe')->default(false);
            $table->boolean('is_independent')->default(false);      // can practice independently
            $table->text('scope_notes')->nullable();
            $table->integer('sort_order')->default(0);
        });

        // Board Certification Types (reference catalog)
        Schema::create('board_certification_types', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 50)->unique();
            $table->string('abbreviation', 30);
            $table->string('name', 150);
            $table->string('issuing_body', 100);     // ABMS, ANCC, NBCC, ASWB, etc.
            $table->string('discipline', 50);
            $table->string('specialty', 100)->nullable();
            $table->integer('recert_years')->nullable();  // years between recertification
            $table->boolean('requires_cme')->default(true);
            $table->text('notes')->nullable();
        });

        // Denial Reasons
        Schema::create('denial_reasons', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 50)->unique();
            $table->string('name', 100);
            $table->string('category', 30);          // documentation, eligibility, capacity, compliance, administrative
            $table->text('description')->nullable();
            $table->text('recommended_action')->nullable();
            $table->boolean('is_resubmittable')->default(true);
            $table->integer('sort_order')->default(0);
        });

        // Insurance Plan Types
        Schema::create('insurance_plan_types', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 30)->unique();
            $table->string('name', 50);
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('insurance_plan_types');
        Schema::dropIfExists('denial_reasons');
        Schema::dropIfExists('board_certification_types');
        Schema::dropIfExists('license_types');
        Schema::dropIfExists('billing_modifiers');
        Schema::dropIfExists('place_of_service_codes');
        Schema::dropIfExists('cpt_codes');
        Schema::dropIfExists('document_types');
        Schema::dropIfExists('states');
    }
};
