<?php

// Provider-scoped operator notes. Mirrors patient_notes but keyed by
// provider_id (FK) — providers are first-class rows here, so we don't
// need the patient_key normalization trick.
//
// Scope: per agency via the standard TenantScope path. providers.agency_id
// is canonical; the duplicated agency_id column makes queries faster
// without an extra join and stays consistent with the rest of the
// provider_* tables.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained('agencies')->cascadeOnDelete();
            $table->foreignId('provider_id')->constrained('providers')->cascadeOnDelete();
            $table->text('body');
            $table->boolean('pinned')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['agency_id', 'provider_id'], 'provider_notes_agency_provider_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_notes');
    }
};
