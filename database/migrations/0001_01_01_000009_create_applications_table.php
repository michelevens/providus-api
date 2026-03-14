<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->string('legacy_id', 50)->nullable();
            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payer_id')->constrained();
            $table->foreignId('payer_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('payer_name')->nullable();
            $table->string('state', 5);
            $table->string('type', 20)->default('individual');
            $table->smallInteger('wave')->default(1);
            $table->string('status', 20)->default('not_started');
            $table->string('portal_url', 500)->nullable();
            $table->string('application_ref', 100)->nullable();
            $table->string('enrollment_id', 100)->nullable();
            $table->date('submitted_date')->nullable();
            $table->date('received_date')->nullable();
            $table->date('effective_date')->nullable();
            $table->text('denial_reason')->nullable();
            $table->decimal('est_monthly_revenue', 10, 2)->default(0);
            $table->string('payer_contact_name', 100)->nullable();
            $table->string('payer_contact_phone', 30)->nullable();
            $table->string('payer_contact_email')->nullable();
            $table->text('notes')->nullable();
            $table->jsonb('tags')->default('[]');
            $table->timestamps();

            $table->index('agency_id');
            $table->index('provider_id');
            $table->index('payer_id');
            $table->index('status');
            $table->index('state');
            $table->index('wave');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};
