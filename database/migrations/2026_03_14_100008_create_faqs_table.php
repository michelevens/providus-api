<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faqs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->nullable()->constrained()->cascadeOnDelete(); // null = global
            $table->string('category')->default('general');
            $table->string('question');
            $table->text('answer');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_published')->default(true);
            $table->integer('helpful_count')->default(0);
            $table->timestamps();

            $table->index(['agency_id', 'category', 'is_published']);
        });

        // Licensing boards reference table
        Schema::create('licensing_boards', function (Blueprint $table) {
            $table->id();
            $table->string('state', 2);
            $table->string('board_name');
            $table->string('board_type')->nullable(); // medical, nursing, dental, pharmacy, psychology, etc.
            $table->string('website_url')->nullable();
            $table->string('verification_url')->nullable();
            $table->string('renewal_url')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('state');
            $table->index('board_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('licensing_boards');
        Schema::dropIfExists('faqs');
    }
};
