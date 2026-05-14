<?php

// Extend communication_logs to support threaded async messaging
// (email today, optionally inbound replies + patient SMS later)
// without creating a parallel "messages" table.
//
// communication_logs already covers single-shot interactions —
// phone calls, fax confirmations, portal submissions, log-only
// emails — driven by LogCallModal and PayerFollowup workflows.
// Threaded conversations layer on top via thread_id grouping:
//
//   thread_id = NULL  → standalone interaction (phone call, etc.)
//   thread_id = X     → a message in conversation X. The first
//                       message in the thread has parent_id = NULL.
//                       Subsequent messages reference parent_id =
//                       the immediately preceding message in the
//                       thread. (We use parent_id, not just a flat
//                       thread_id, because future inbound replies
//                       may attach to a specific outbound message.)
//
// recipient_email + recipient_name capture the destination of an
// email; for inbound replies they capture the FROM of the incoming
// message. Existing contact_name / contact_info are now reserved
// for legacy phone/fax records.
//
// delivery_status mirrors Resend's lifecycle: queued → sent →
// delivered | bounced | complained. resend_id is the
// provider message id for webhook correlation.
//
// html_body is the rendered email HTML; body remains the
// operator's plain-text composition. Storing both means we can
// re-display the original AND show the recipient what was
// actually sent.
//
// entity_type + entity_id generalize beyond the existing
// application_id / provider_id columns so a message can attach
// to claim / billing_client / payer_followup / etc. The legacy
// columns stay nullable for backward compatibility.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('communication_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('thread_id')->nullable()->after('id');
            $table->unsignedBigInteger('parent_id')->nullable()->after('thread_id');
            $table->unsignedBigInteger('claim_id')->nullable()->after('provider_id');
            $table->unsignedBigInteger('billing_client_id')->nullable()->after('claim_id');
            $table->string('entity_type', 32)->nullable()->after('billing_client_id');
            $table->unsignedBigInteger('entity_id')->nullable()->after('entity_type');
            $table->string('recipient_email')->nullable()->after('contact_info');
            $table->string('recipient_name')->nullable()->after('recipient_email');
            $table->string('delivery_status', 32)->nullable()->after('outcome');
            $table->string('resend_id')->nullable()->after('delivery_status');
            $table->longText('html_body')->nullable()->after('body');
            $table->timestamp('delivered_at')->nullable()->after('resend_id');
            $table->timestamp('bounced_at')->nullable()->after('delivered_at');
            $table->timestamp('read_at')->nullable()->after('bounced_at');

            $table->index('thread_id');
            $table->index('parent_id');
            $table->index('claim_id');
            $table->index('billing_client_id');
            $table->index(['entity_type', 'entity_id']);
            $table->index('recipient_email');
            $table->index('resend_id');
        });
    }

    public function down(): void
    {
        Schema::table('communication_logs', function (Blueprint $table) {
            $table->dropIndex(['communication_logs_thread_id_index']);
            $table->dropIndex(['communication_logs_parent_id_index']);
            $table->dropIndex(['communication_logs_claim_id_index']);
            $table->dropIndex(['communication_logs_billing_client_id_index']);
            $table->dropIndex(['communication_logs_entity_type_entity_id_index']);
            $table->dropIndex(['communication_logs_recipient_email_index']);
            $table->dropIndex(['communication_logs_resend_id_index']);
            $table->dropColumn([
                'thread_id', 'parent_id', 'claim_id', 'billing_client_id',
                'entity_type', 'entity_id', 'recipient_email', 'recipient_name',
                'delivery_status', 'resend_id', 'html_body', 'delivered_at',
                'bounced_at', 'read_at',
            ]);
        });
    }
};
