<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Auth events log — separate from audit_logs because the shape is
 * different: many entries have no auditable model (failed logins,
 * lockouts), and we want to query them on different axes than
 * "what changed on this row".
 *
 * Captured events:
 *   - login_success
 *   - login_failed (bad password, unknown email, deactivated account)
 *   - logout
 *   - two_factor_required (login challenged but not yet passed)
 *   - two_factor_success
 *   - two_factor_failed
 *   - lockout (rate-limit hit on a single email/IP)
 *   - password_reset_requested / password_reset_completed
 *   - impersonation_started / impersonation_ended
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('auth_events', function (Blueprint $table) {
            $table->id();
            // Nullable: failed logins where the email didn't resolve to a
            // user. We still want to log the attempt for rate-limit /
            // brute-force pattern detection.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('agency_id')->nullable()->constrained()->nullOnDelete();
            // Email as typed by the attempt — preserved even when user_id
            // is null. Useful for "is someone hammering this address?"
            $table->string('email')->nullable();
            $table->string('event_type', 40);
            // Optional structured payload: { reason, login_method, ... }
            // Stored as JSON so different events can carry different shapes.
            $table->json('metadata')->nullable();
            // Impersonation correlation: when the operator initiates an
            // impersonation, both the operator user_id and the target
            // tenant's id are recorded. impersonator_user_id is also set
            // when a subsequent action happens during the session.
            $table->foreignId('impersonator_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['email', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['event_type', 'created_at']);
            $table->index(['agency_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auth_events');
    }
};
