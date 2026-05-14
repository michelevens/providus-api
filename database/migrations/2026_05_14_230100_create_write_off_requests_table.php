<?php

// Write-off approval requests — the durable record of a pending
// write-off that's waiting on someone else to approve.
//
// Why a dedicated table instead of reusing billing_tasks (the existing
// owner-approval queue):
//   - The org-approval path needs a public, unauthenticated URL with
//     a signed token. billing_tasks doesn't have that field.
//   - Org approvers aren't users in our system; we email the
//     billing_client.contact_email. The token IS the auth.
//   - Decided_by needs to handle both User (when an internal owner
//     approves) and a non-user email (when the org approves via
//     the portal). Two columns avoid weird FK constraints.
//   - Linking back to a specific claim is more natural here than
//     stuffing claim_id into billing_tasks.linkable_id.
//
// State machine:
//   pending -> approved (decision recorded, applyWriteOff fired, claim now status=written_off)
//   pending -> rejected (closed, claim unchanged, agency notified)
//   pending -> expired  (fallback_to_owner_after_days elapsed; auto-converted
//                       into a billing_tasks owner approval, this row's
//                       status flips to 'escalated_to_owner')
//   pending -> cancelled (agency operator withdraws the request)

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('write_off_requests', function (Blueprint $table) {
            $table->id();

            // Scope
            $table->foreignId('agency_id')->constrained('agencies')->cascadeOnDelete();
            $table->foreignId('claim_id')->constrained('claims')->cascadeOnDelete();
            $table->foreignId('billing_client_id')->nullable()->constrained('billing_clients')->nullOnDelete();

            // The proposed write-off
            $table->decimal('amount', 12, 2);
            $table->string('category', 50)->nullable();
            $table->text('reason');

            // Who requested + when
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('requested_at')->useCurrent();

            // Who the request is waiting on
            $table->enum('approver_type', ['org', 'owner'])->index();
            $table->string('approver_email')->nullable();   // populated for org_required, null for owner_required
            $table->string('portal_token', 64)->nullable()->unique(); // populated only for org_required
            $table->timestamp('expires_at')->nullable();    // when the org_required path falls back to owner

            // State
            $table->enum('status', ['pending', 'approved', 'rejected', 'expired', 'cancelled', 'escalated_to_owner'])
                ->default('pending')
                ->index();
            $table->timestamp('decided_at')->nullable();
            $table->string('decided_by_email')->nullable();  // raw email (org approver doesn't have a User row)
            $table->foreignId('decided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('decision_reason')->nullable();     // for rejections; optional for approvals

            // When applied, the AppliedWriteOff records the resulting
            // claim adjustment. Just tracks the link back, no
            // cascade — the claim's notes carry the audit marker
            // independently.
            $table->timestamp('applied_at')->nullable();

            $table->timestamps();

            $table->index(['claim_id', 'status']);
            $table->index(['agency_id', 'status', 'requested_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('write_off_requests');
    }
};
