<?php

namespace App\Support;

use App\Models\AuthEvent;
use Illuminate\Http\Request;

/**
 * Centralized auth-event recorder.
 *
 * AuthController calls these directly — the codebase uses manual
 * Hash::check rather than Auth::attempt, so the framework's own
 * Login/Failed/Logout events don't fire on our paths. We also wire
 * the framework events as a defense-in-depth fallback (see
 * EventServiceProvider) so any code that does use Auth::attempt
 * still lands here.
 *
 * Event types (kept as plain string constants for ease of grepping):
 *   - login_success           — credentials passed, token issued
 *   - login_failed            — wrong password, unknown email, or deactivated
 *   - logout                  — current token revoked
 *   - two_factor_required     — login passed but 2FA challenge pending
 *   - two_factor_success      — challenge passed, full token issued
 *   - two_factor_failed       — wrong code
 *   - lockout                 — rate-limited on this email or IP
 *   - password_reset_requested
 *   - password_reset_completed
 *   - impersonation_started   — superadmin minted impersonation token
 *   - impersonation_ended     — impersonation token revoked
 *
 * Failures never throw — auth logging mustn't break authentication.
 */
class AuthEventLogger
{
    public static function record(
        string $eventType,
        ?int $userId = null,
        ?int $agencyId = null,
        ?string $email = null,
        ?array $metadata = null,
        ?int $impersonatorUserId = null,
        ?Request $request = null,
    ): void {
        try {
            $request = $request ?? request();
            AuthEvent::create([
                'user_id' => $userId,
                'agency_id' => $agencyId,
                'email' => $email,
                'event_type' => $eventType,
                'metadata' => $metadata,
                'impersonator_user_id' => $impersonatorUserId,
                'ip_address' => $request?->ip(),
                'user_agent' => $request?->userAgent(),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Never bubble — auth logging failure must not block login.
            \Log::warning('AuthEventLogger failed: ' . $e->getMessage());
        }
    }
}
