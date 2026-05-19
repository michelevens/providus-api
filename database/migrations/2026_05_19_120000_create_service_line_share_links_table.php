<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * service_line_share_links — tokenized, no-login PDF download links
 * for the per-service-line business plans agencies hand off to clients.
 *
 * The PDF is generated client-side in V2 (jspdf) and uploaded as bytes
 * here; we stash the R2 object key on the row and serve it via a
 * presigned URL on the public endpoint. The token is the only secret —
 * Str::random(40), unguessable, throttled on the public route.
 *
 * Recipient may be a free-form email (sent via Resend) OR a linked
 * organization_id (when the agency is preparing the plan for a client
 * we already model). Both can be set together (post to org docs AND
 * email a copy externally) — phase 1 only wires the email path.
 *
 * View tracking is a hot path (every click bumps view_count); we keep
 * it on the same row instead of a separate event table to avoid an
 * insert on every download.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('service_line_share_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_user_id')->constrained('users');

            // The service line is a hardcoded V2 catalog entry — we
            // store the id ("psych", "facility-mh", "hra-awv") plus the
            // human label snapshotted at send time so the email/landing
            // page survives catalog renames.
            $table->string('service_line_id', 80);
            $table->string('service_line_name', 200);

            // Optional org link — when the plan was prepared FOR a
            // tenant we model. Phase 2 wires this to OrganizationDetail
            // Documents tab.
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();

            // External recipient (phase 1: email-out flow)
            $table->string('recipient_email', 254)->nullable();
            $table->string('recipient_name', 200)->nullable();

            // Optional personalized note from the sender
            $table->text('message')->nullable();

            // Token (Str::random(40), alphanumeric) + R2 key for the PDF
            $table->string('public_token', 64)->unique();
            $table->string('r2_key', 500);
            $table->string('original_filename', 200);
            $table->unsignedInteger('file_size')->default(0);
            $table->string('file_disk', 20)->default('s3');

            // Expiry — defaults to 90 days in the controller. NULL means
            // "no expiry"; we treat that as 1-year hard ceiling at read
            // time as defense-in-depth.
            $table->timestamp('expires_at')->nullable();

            // View tracking
            $table->unsignedInteger('view_count')->default(0);
            $table->timestamp('last_viewed_at')->nullable();
            $table->string('last_viewed_ip', 64)->nullable();

            // Email-send tracking
            $table->timestamp('email_sent_at')->nullable();

            $table->timestamps();

            $table->index(['agency_id', 'service_line_id']);
            $table->index('organization_id');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_line_share_links');
    }
};
