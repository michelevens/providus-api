<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Service catalog — predefined credentialing services
        Schema::create('service_catalog', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 20)->nullable();
            $table->text('description')->nullable();
            $table->string('category')->nullable(); // credentialing, licensing, enrollment, consulting, other
            $table->decimal('default_price', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('agency_id');
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->string('invoice_number')->unique();
            $table->string('type', 20)->default('invoice'); // invoice, estimate
            $table->string('status', 20)->default('draft'); // draft, sent, viewed, partial, paid, overdue, cancelled
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->string('client_name')->nullable();
            $table->string('client_email')->nullable();
            $table->text('client_address')->nullable();
            $table->date('issue_date');
            $table->date('due_date');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('balance_due', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->text('terms')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['agency_id', 'status']);
            $table->index(['agency_id', 'due_date']);
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_catalog_id')->nullable()->constrained('service_catalog')->nullOnDelete();
            $table->string('description');
            $table->decimal('quantity', 8, 2)->default(1);
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('payment_method')->nullable(); // credit_card, ach, check, wire, cash, other
            $table->string('reference_number')->nullable();
            $table->date('payment_date');
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['agency_id', 'invoice_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('service_catalog');
    }
};
