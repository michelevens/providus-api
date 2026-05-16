<?php

// Snooze + Promise-to-Pay tracking on patient_statements.
//
// Two mechanisms that both temporarily remove a statement from the
// active Balance Reminders queue, but for different reasons:
//
// SNOOZE — operator wants this row to go away for a fixed period
// (typical: "I just talked to the patient, check arrives in 7 days").
// No expected payment recorded; pure UX. Row returns to the queue
// after snoozed_until passes.
//
// PROMISE-TO-PAY — operator has a structured commitment from the
// patient: amount + date. Row hides until the promised date. If a
// matching payment lands first, status flips to paid (no badge).
// If the date passes without payment, promise_broken_at is stamped
// and the row reappears in the queue with a 'BROKEN PROMISE' badge.
// This is the metric we actually want to track ("what % of patient
// promises are kept?").
//
// Both columns are nullable — most rows never have a promise/snooze
// set. The Balance Reminders list filter ignores rows where either
// timestamp is in the future.
//
// Why not a separate snoozes/promises table:
// One snooze/promise per statement at a time — operator clearing it
// would just nullable. No history needed yet. If we ever want
// promise-keeping analytics across multiple promises per statement,
// we'd add a `statement_promises` table and migrate the columns there.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patient_statements', function (Blueprint $table) {
            // Snooze — pure UX pause. NULL or past = active in queue.
            $table->timestampTz('snoozed_until')->nullable()->after('last_sent_date');
            $table->foreignId('snoozed_by')->nullable()->after('snoozed_until')->constrained('users')->nullOnDelete();
            $table->string('snooze_reason', 200)->nullable()->after('snoozed_by');

            // Promise to pay — structured commitment. Both date and
            // amount are required when set (validated at the
            // controller level, not the DB).
            $table->date('promised_pay_date')->nullable()->after('snooze_reason');
            $table->decimal('promised_pay_amount', 10, 2)->nullable()->after('promised_pay_date');
            $table->foreignId('promised_pay_by')->nullable()->after('promised_pay_amount')->constrained('users')->nullOnDelete();
            $table->text('promise_notes')->nullable()->after('promised_pay_by');
            // Stamped lazily by the list endpoint when promised_pay_date
            // is in the past + no matching payment has landed. Null
            // until that detection fires.
            $table->timestampTz('promise_broken_at')->nullable()->after('promise_notes');

            // Index the two timestamps so the active-queue filter
            // (snoozed_until IS NULL OR snoozed_until < now()) is fast
            // once we have a few hundred patient statements.
            $table->index('snoozed_until', 'patient_statements_snoozed_until_idx');
            $table->index('promised_pay_date', 'patient_statements_promised_pay_date_idx');
        });
    }

    public function down(): void
    {
        Schema::table('patient_statements', function (Blueprint $table) {
            $table->dropIndex('patient_statements_promised_pay_date_idx');
            $table->dropIndex('patient_statements_snoozed_until_idx');
            $table->dropForeign(['promised_pay_by']);
            $table->dropForeign(['snoozed_by']);
            $table->dropColumn([
                'snoozed_until', 'snoozed_by', 'snooze_reason',
                'promised_pay_date', 'promised_pay_amount', 'promised_pay_by',
                'promise_notes', 'promise_broken_at',
            ]);
        });
    }
};
