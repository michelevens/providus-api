<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Global payer catalog — not tenant-scoped
        Schema::create('payers', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 50)->unique();
            $table->string('name');
            $table->string('short_name', 50)->nullable();
            $table->string('category', 30);
            $table->string('region', 20)->nullable();
            $table->string('parent_org', 100)->nullable();
            $table->string('stedi_id', 20)->nullable();
            $table->jsonb('states')->default('[]');
            $table->decimal('market_share', 5, 2)->nullable();
            $table->integer('avg_cred_days')->nullable();
            $table->string('credentialing_url', 500)->nullable();
            $table->string('cred_phone', 30)->nullable();
            $table->string('cred_email')->nullable();
            $table->string('logo_slug', 50)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payers');
    }
};
