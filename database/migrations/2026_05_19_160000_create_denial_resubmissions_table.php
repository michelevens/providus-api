<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * denial_resubmissions — one row per appeal/resubmission attempt
 * against a ClaimDenial. The denial row itself already tracks the
 * latest appeal_level + appeal_submitted_date + recovered_amount as
 * summary state; this table preserves the full audit trail.
 *
 * Why a separate table instead of a JSON array on the denial:
 *  - Each attempt has its own resubmitted_claim_number from the payer,
 *    which we want to index for cross-reference from the claims search.
 *  - Outcome per attempt (won / partial / denied / no response) needs
 *    structured filtering for the "recovery report" we're building.
 *  - Attachments per attempt belong on the row, not flattened into the
 *    denial.
 *
 * Lifecycle: status transitions submitted -> awaiting_response ->
 * (won | partial | denied | abandoned). attempt_number is 1-indexed
 * and unique per denial (we enforce a soft unique constraint — DB
 * allows multiple in-flight resubmissions if the workflow ever calls
 * for it).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('denial_resubmissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('claim_denial_id')->constrained('claim_denials')->cascadeOnDelete();

            // 1 = first appeal, 2 = second appeal, etc. Maps to the
            // existing claim_denials.appeal_level column for summary.
            $table->unsignedTinyInteger('attempt_number');

            // Status lifecycle. submitted = paperwork out the door,
            // awaiting_response = payer ACK received but no decision
            // yet, won = full recovery, partial = some recovery,
            // denied = payer upheld original denial, abandoned = we
            // gave up (write-off path).
            $table->string('status', 20)->default('submitted');

            // What we sent + when
            $table->date('submitted_date');
            $table->string('submission_method', 30)->nullable(); // portal, fax, mail, edi_resubmit, payer_phone, peer_review
            $table->text('submission_notes')->nullable();

            // The payer's new identifier for this resubmission (the
            // ICN / claim number they issue on the corrected/resubmitted
            // 837 or appeal letter). Indexed for cross-search.
            $table->string('resubmitted_claim_number')->nullable();
            $table->string('payer_appeal_id')->nullable(); // some payers issue a separate appeal-tracking number

            // Outcome
            $table->date('decision_date')->nullable();
            $table->decimal('recovered_amount', 12, 2)->default(0);
            $table->text('outcome_notes')->nullable();

            // Optional attachments — same JSON shape as
            // claim_denials.attachments (R2 keys + metadata).
            $table->json('attachments')->nullable();

            // Audit
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['agency_id', 'status']);
            $table->index('claim_denial_id');
            $table->index('resubmitted_claim_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('denial_resubmissions');
    }
};
