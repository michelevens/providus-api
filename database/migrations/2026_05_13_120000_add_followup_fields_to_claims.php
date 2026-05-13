<?php

// Stale-claim follow-up workflow fields.
//
// Pending Claims surfaces 67 submitted-but-unanswered claims; 58 are
// 30d+ old and at timely-filing risk. To turn the "Check" + "Check All"
// buttons from toasts into real workflow we need somewhere to put:
//   - the last Availity 276/277 status response (claim + child log)
//   - who owns the claim today (assigned_to)
//   - when it should next be worked (follow_up_due_date / snoozed_until)
//   - whether it's been escalated past first-line follow-up
//
// claim_status_checks captures every 276 inquiry so we can trend
// payer-by-payer how often a "submitted" claim actually surfaces as
// paid on the payer side but pending in our ledger.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('claims', function (Blueprint $table) {
            $table->timestamp('last_status_check_at')->nullable()->after('notes');
            $table->string('last_status_code', 16)->nullable()->after('last_status_check_at');
            $table->string('last_status_category', 16)->nullable()->after('last_status_code');
            $table->json('last_status_response')->nullable()->after('last_status_category');
            $table->unsignedInteger('status_inquiry_count')->default(0)->after('last_status_response');
            $table->foreignId('assigned_to')->nullable()->after('status_inquiry_count')->constrained('users')->nullOnDelete();
            $table->date('follow_up_due_date')->nullable()->after('assigned_to');
            $table->date('snoozed_until')->nullable()->after('follow_up_due_date');
            $table->boolean('escalated')->default(false)->after('snoozed_until');

            $table->index('assigned_to');
            $table->index('follow_up_due_date');
            $table->index('snoozed_until');
        });

        Schema::create('claim_status_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('claim_id')->constrained('claims')->cascadeOnDelete();
            $table->timestamp('checked_at');
            $table->string('source', 32)->default('availity');
            $table->string('status_code', 16)->nullable();
            $table->string('status_category', 16)->nullable();
            $table->string('status_text')->nullable();
            $table->decimal('paid_amount', 12, 2)->nullable();
            $table->date('paid_date')->nullable();
            $table->string('check_number')->nullable();
            $table->json('raw_response')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['claim_id', 'checked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('claim_status_checks');
        Schema::table('claims', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_to');
            $table->dropColumn([
                'last_status_check_at',
                'last_status_code',
                'last_status_category',
                'last_status_response',
                'status_inquiry_count',
                'follow_up_due_date',
                'snoozed_until',
                'escalated',
            ]);
        });
    }
};
