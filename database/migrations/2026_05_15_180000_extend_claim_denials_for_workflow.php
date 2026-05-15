<?php

// Extends claim_denials for the full denial-management workflow:
// lifecycle states, persistent appeal letters, payer response tracking,
// multi-level appeal lineage, and attachments.
//
// What stays the same (already on claim_denials):
//   denial_category, denial_code, denial_reason, denied_amount,
//   priority, denial_date, appeal_deadline, appeal_level,
//   appeal_submitted_date, recovered_amount, appeal_notes (operator
//   working notes), resolution_notes, assigned_to, created_by,
//   resolved_at, status (varchar — enum extends via app code, no
//   schema change needed for the new values).
//
// What gets added (this migration):
//   triaged_at / triaged_by — operator classified + decided to work
//   letter_text — the actual appeal letter content (mutable)
//   letter_drafted_at / letter_drafted_by — when generated
//   letter_sent_at / letter_sent_by / letter_sent_method —
//     mail / fax / portal / email
//   payer_response_at / payer_response_text /
//     payer_response_outcome — overturned / upheld / partial
//   parent_denial_id — points back to the previous-level denial when
//     this row is an escalation (e.g. level-2 appeal); enables
//     multi-level appeal history
//   attachments — jsonb array of {label, url, content_type, size_bytes,
//     uploaded_at, uploaded_by}; URLs point to Cloudflare R2 (Phase 5)
//
// Status enum extension (no schema change; varchar):
//   new → triaged → letter_drafted → letter_sent →
//   awaiting_response → payer_responded → escalated |
//   resolved_recovered | resolved_upheld | resolved_written_off

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('claim_denials', function (Blueprint $table) {
            // Triage stage — operator classified the denial + decided
            // to work it (vs. write it off immediately).
            $table->timestampTz('triaged_at')->nullable()->after('appeal_deadline');
            $table->foreignId('triaged_by')->nullable()->after('triaged_at')->constrained('users')->nullOnDelete();

            // Appeal letter — the actual content we generated, mutable
            // so operator can edit before sending. appeal_notes (which
            // already exists) stays for operator working-notes;
            // letter_text is the formal artifact we generate PDFs from.
            $table->longText('letter_text')->nullable()->after('triaged_by');
            $table->timestampTz('letter_drafted_at')->nullable()->after('letter_text');
            $table->foreignId('letter_drafted_by')->nullable()->after('letter_drafted_at')->constrained('users')->nullOnDelete();

            // Letter sent — stamps the moment we mailed/faxed/uploaded.
            $table->timestampTz('letter_sent_at')->nullable()->after('letter_drafted_by');
            $table->foreignId('letter_sent_by')->nullable()->after('letter_sent_at')->constrained('users')->nullOnDelete();
            $table->string('letter_sent_method', 20)->nullable()->after('letter_sent_by'); // mail | fax | portal | email

            // Payer response — what they said after we appealed.
            $table->timestampTz('payer_response_at')->nullable()->after('letter_sent_method');
            $table->text('payer_response_text')->nullable()->after('payer_response_at');
            $table->string('payer_response_outcome', 20)->nullable()->after('payer_response_text'); // overturned | upheld | partial

            // Multi-level appeal lineage. When operator escalates a
            // level-1 denial to level-2, a NEW claim_denials row is
            // created with appeal_level=2 and parent_denial_id pointing
            // at the level-1 row. This makes the appeal history a tree.
            // Self-reference, nullOnDelete (deleting a parent
            // shouldn't cascade-delete children — they're historical).
            $table->foreignId('parent_denial_id')->nullable()->after('payer_response_outcome')->constrained('claim_denials')->nullOnDelete();

            // Attachments — array of {label, url, content_type,
            // size_bytes, uploaded_at, uploaded_by_user_id}. Cloudflare
            // R2 stores the actual files (Phase 5); this column tracks
            // the references + metadata. jsonb so we can query/index
            // it later if needed (e.g. "denials with at least one
            // chart_note attachment").
            $table->jsonb('attachments')->nullable()->after('parent_denial_id');

            // Index for the "awaiting response" filter — finds denials
            // where letter_sent_at is set but payer_response_at isn't.
            // Common operator query: "what are we still waiting on?"
            $table->index(['letter_sent_at', 'payer_response_at'], 'claim_denials_awaiting_idx');
            // Index for the parent_denial_id lookup — when rendering
            // the appeal-history tree for a single denial.
            $table->index('parent_denial_id', 'claim_denials_parent_idx');
        });
    }

    public function down(): void
    {
        Schema::table('claim_denials', function (Blueprint $table) {
            $table->dropIndex('claim_denials_parent_idx');
            $table->dropIndex('claim_denials_awaiting_idx');
            $table->dropForeign(['parent_denial_id']);
            $table->dropForeign(['letter_sent_by']);
            $table->dropForeign(['letter_drafted_by']);
            $table->dropForeign(['triaged_by']);
            $table->dropColumn([
                'triaged_at', 'triaged_by',
                'letter_text', 'letter_drafted_at', 'letter_drafted_by',
                'letter_sent_at', 'letter_sent_by', 'letter_sent_method',
                'payer_response_at', 'payer_response_text', 'payer_response_outcome',
                'parent_denial_id', 'attachments',
            ]);
        });
    }
};
