<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agencies', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->default(DB::raw('gen_random_uuid()'));
            $table->string('name');
            $table->string('slug', 100)->unique();
            $table->string('npi', 10)->nullable();
            $table->string('tax_id', 20)->nullable();
            $table->string('address_street')->nullable();
            $table->string('address_city', 100)->nullable();
            $table->string('address_state', 2)->nullable();
            $table->string('address_zip', 10)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->string('taxonomy', 20)->nullable();
            $table->string('logo_url', 500)->nullable();
            $table->string('primary_color', 7)->default('#2C4A5A');
            $table->string('accent_color', 7)->default('#D4A855');
            $table->string('plan_tier', 20)->default('starter');
            $table->boolean('is_active')->default(true);
            // Embed/widget config
            $table->jsonb('allowed_domains')->default('[]'); // domains allowed to embed
            $table->string('embed_theme', 20)->default('default');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agencies');
    }
};
