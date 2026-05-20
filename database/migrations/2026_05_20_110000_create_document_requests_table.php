<?php

// Agency-initiated document requests sent to organizations or
// individual providers. Each request can ask for multiple documents
// (stored as a JSON checklist) and supports two delivery modes:
//
//   - portal:  recipient logs into V2 and sees the request in their
//              dashboard. Uploads files there. Requires recipient
//              email match a User row with the right role+FK.
//   - email:   a tokenized URL goes out via Resend. Recipient clicks
//              the link, lands on a no-auth upload page, drops files
//              keyed to the same checklist items. Same lifecycle.
//
// Both modes can fire simultaneously.
//
// Lifecycle:
//   pending  → agency sent the request, nothing uploaded yet
//   partial  → some items received, others still missing
//   fulfilled → every checklist item has at least one upload
//   cancelled → agency revoked the request
//   expired   → expires_at passed and not fully fulfilled
//
// File storage: uploads land in the OWNING entity's documents table
// (organization_documents or provider_documents) — NOT here. This
// table just tracks the ask + completion state. The
// document_request_id FK on those tables links uploads back.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users');

            // Polymorphic target: organization OR provider. Only ONE of
            // these is set per row; we constrain via a check (or in
            // controller validation) rather than a polymorphic morph
            // pair so queries by FK stay simple and indexable.
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->foreignId('provider_id')->nullable()->constrained('providers')->nullOnDelete();

            // The checklist. Array of {key, label, description?,
            // required?, expected_doc_type?}. Each item is a logical
            // slot the recipient uploads against. Example:
            //   [{ "key": "w9", "label": "W-9 (signed)", "required": true },
            //    { "key": "voided_check", "label": "Voided check", "required": true }]
            $table->json('items');

            // Recipient address fields. For portal-mode the recipient
            // is identified by email-matching a User row at delivery
            // time; we keep recipient_email here too so the audit
            // trail survives even if the User account is later removed
            // or transferred.
            $table->string('recipient_email', 254);
            $table->string('recipient_name', 200)->nullable();

            // Free-text note from the agency to the recipient
            // ("We need these to process your BCBS-NM credentialing
            // before March 1"). Renders in the email + portal card.
            $table->text('message')->nullable();

            // Public token for the email-mode URL. Always generated
            // (even if delivery_mode='portal') so the agency can copy
            // and share manually if needed.
            $table->string('public_token', 64)->unique();

            // 'portal' | 'email' | 'both' — which delivery channel(s)
            // were used at send time. Email-only doesn't require a
            // matching User row.
            $table->string('delivery_mode', 10)->default('both');

            // Lifecycle state — see header comment.
            $table->string('status', 20)->default('pending');

            // Per-attempt email send tracking. Reset to NULL if the
            // agency resends.
            $table->timestamp('email_sent_at')->nullable();
            $table->timestamp('first_viewed_at')->nullable();
            $table->timestamp('first_uploaded_at')->nullable();
            $table->timestamp('fulfilled_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();

            // Optional auto-expire. NULL = no expiry beyond a 1-year
            // hard cap enforced at read time (defense-in-depth — same
            // pattern as service-line share links).
            $table->timestamp('expires_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['agency_id', 'status']);
            $table->index('organization_id');
            $table->index('provider_id');
            $table->index('recipient_email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_requests');
    }
};
