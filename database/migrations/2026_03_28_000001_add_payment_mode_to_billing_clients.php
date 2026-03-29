<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_clients', function (Blueprint $table) {
            $table->string('payment_mode', 20)->default('self_managed')->after('fee_structure'); // agency_managed, self_managed
            $table->decimal('agency_fee_percent', 5, 2)->default(0)->after('payment_mode'); // e.g. 7.00 = 7%
        });

        // Client payment ledger — tracks collections, fees, and remittances per client per period
        Schema::create('client_payment_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('billing_client_id')->constrained()->cascadeOnDelete();
            $table->string('period', 7); // YYYY-MM
            $table->decimal('total_collected', 12, 2)->default(0);
            $table->decimal('agency_fee', 12, 2)->default(0);
            $table->decimal('amount_remitted', 12, 2)->default(0);
            $table->decimal('outstanding', 12, 2)->default(0); // collected - fee - remitted
            $table->date('remittance_date')->nullable();
            $table->string('remittance_method', 30)->nullable(); // check, ach, wire
            $table->string('remittance_reference')->nullable(); // check # or transfer ID
            $table->string('status', 20)->default('pending'); // pending, partial, remitted
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['agency_id', 'billing_client_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_payment_ledger');
        Schema::table('billing_clients', function (Blueprint $table) {
            $table->dropColumn(['payment_mode', 'agency_fee_percent']);
        });
    }
};
