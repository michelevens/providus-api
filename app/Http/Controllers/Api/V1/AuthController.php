<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Mail\PasswordReset as PasswordResetMail;
use App\Models\Agency;
use App\Models\AgencyConfig;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Register a new agency + owner account.
     */
    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'agency_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => ['required', 'string', 'min:8', 'confirmed', 'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^a-zA-Z\d]).+$/'],
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
        ]);

        $slug = Str::slug($request->agency_name);
        $baseSlug = $slug;
        $counter = 1;
        while (Agency::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $counter++;
        }

        $agency = Agency::create([
            'name' => $request->agency_name,
            'slug' => $slug,
            'email' => $request->email,
        ]);

        AgencyConfig::create(['agency_id' => $agency->id]);

        $user = User::create([
            'agency_id' => $agency->id,
            'email' => $request->email,
            'password' => $request->password,
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'role' => 'owner',
        ]);

        $token = $user->createToken('auth-token')->plainTextToken;

        // Send welcome email via Resend
        try {
            $resendKey = config('services.resend.key');
            if ($resendKey) {
                \Illuminate\Support\Facades\Http::withToken($resendKey)->post('https://api.resend.com/emails', [
                    'from' => config('mail.from.address', 'noreply@credentik.com'),
                    'to' => [$user->email],
                    'subject' => 'Welcome to Credentik!',
                    'html' => '<div style="font-family:Inter,sans-serif;max-width:560px;margin:0 auto;padding:40px 20px;">'
                        . '<div style="text-align:center;margin-bottom:32px;">'
                        . '<div style="display:inline-block;background:#0891b2;color:#fff;font-size:24px;font-weight:800;padding:12px 20px;border-radius:12px;">Credentik</div>'
                        . '</div>'
                        . '<h1 style="font-size:24px;font-weight:700;color:#111827;margin:0 0 16px;">Welcome, ' . e($user->first_name) . '!</h1>'
                        . '<p style="font-size:15px;color:#4b5563;line-height:1.6;">Your Credentik account has been created for <strong>' . e($agency->name) . '</strong>.</p>'
                        . '<p style="font-size:15px;color:#4b5563;line-height:1.6;">You can now:</p>'
                        . '<ul style="font-size:14px;color:#4b5563;line-height:1.8;">'
                        . '<li>Add your providers and start credentialing</li>'
                        . '<li>Track applications across 226+ payers</li>'
                        . '<li>Monitor licenses and compliance</li>'
                        . '<li>Generate reports and share progress</li>'
                        . '</ul>'
                        . '<div style="text-align:center;margin:32px 0;">'
                        . '<a href="https://credentik.com" style="display:inline-block;background:#0891b2;color:#fff;padding:14px 32px;border-radius:10px;font-weight:600;font-size:15px;text-decoration:none;">Go to Dashboard</a>'
                        . '</div>'
                        . '<p style="font-size:13px;color:#9ca3af;text-align:center;">Questions? Reply to this email or contact support.</p>'
                        . '</div>',
                ]);
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Welcome email failed: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'token' => $token,
            'user' => $user->load('agency'),
        ], 201);
    }

    /**
     * Login and return JWT token.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (!$user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Your account has been deactivated.'],
            ]);
        }

        // If 2FA is enabled, return a challenge instead of a token
        if ($user->two_factor_enabled && $user->two_factor_secret) {
            $loginToken = hash('sha256', $user->email . now()->format('Y-m-d-H'));
            return response()->json([
                'success' => true,
                'two_factor_required' => true,
                'user_id' => $user->id,
                'login_token' => $loginToken,
            ]);
        }

        $user->update(['last_login_at' => now()]);
        // Clean up old tokens (keep only last 5) to prevent token bloat
        $tokens = $user->tokens()->orderByDesc('created_at')->get();
        if ($tokens->count() > 5) {
            $tokens->slice(5)->each->delete();
        }
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'token' => $token,
            'user' => $user->load($this->userRelations($user)),
        ]);
    }

    /**
     * Logout — revoke current token.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['success' => true, 'message' => 'Logged out']);
    }

    /**
     * Get current authenticated user.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'user' => $user->load($this->userRelations($user)),
        ]);
    }

    /**
     * Demo login — passwordless auth for demo accounts only.
     * Only works for @demo.credentik.com email addresses.
     * Auto-creates demo accounts if they don't exist.
     */
    public function demoLogin(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);

        // Only allow demo domain
        if (!str_ends_with($request->email, '@demo.credentik.com')) {
            return response()->json([
                'message' => 'Demo login is only available for demo accounts.',
            ], 403);
        }

        // Ensure demo agency exists
        $demoAgency = Agency::firstOrCreate(
            ['slug' => 'demo-agency'],
            [
                'uuid' => (string) \Illuminate\Support\Str::uuid(),
                'name' => 'Demo Credentialing Agency',
                'slug' => 'demo-agency',
                'npi' => '1234567890',
                'tax_id' => '12-3456789',
                'address_street' => '100 Demo Boulevard, Suite 200',
                'address_city' => 'Orlando',
                'address_state' => 'FL',
                'address_zip' => '32801',
                'phone' => '(555) 123-4567',
                'email' => 'demo@credentik.com',
                'taxonomy' => '2084P0800X',
                'plan_tier' => 'professional',
                'is_active' => true,
            ]
        );

        // Ensure agency config exists
        \App\Models\AgencyConfig::firstOrCreate(['agency_id' => $demoAgency->id]);

        // Define demo account profiles
        $demoProfiles = [
            'agency@demo.credentik.com' => ['first_name' => 'Alex', 'last_name' => 'Agency', 'role' => 'agency', 'ui_role' => 'agency'],
            'staff@demo.credentik.com' => ['first_name' => 'Sam', 'last_name' => 'Staff', 'role' => 'agency', 'ui_role' => 'staff'],
            'org@demo.credentik.com' => ['first_name' => 'Olivia', 'last_name' => 'Org', 'role' => 'organization', 'ui_role' => 'organization'],
            'provider@demo.credentik.com' => ['first_name' => 'Pat', 'last_name' => 'Provider', 'role' => 'provider', 'ui_role' => 'provider'],
        ];

        $profile = $demoProfiles[$request->email] ?? ['first_name' => 'Demo', 'last_name' => 'User', 'role' => 'agency'];

        // Find or create the user
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            // Auto-create demo account
            $user = User::create([
                'email' => $request->email,
                'first_name' => $profile['first_name'],
                'last_name' => $profile['last_name'],
                'role' => $profile['role'],
                'ui_role' => $profile['ui_role'] ?? $profile['role'],
                'agency_id' => $demoAgency->id,
                'password' => Hash::make('Demo@2026!'),
                'is_active' => true,
            ]);
        } else {
            // Always update demo user profile and reassign to demo agency
            $user->update([
                'agency_id' => $demoAgency->id,
                'ui_role' => $profile['ui_role'] ?? $profile['role'],
            ]);
        }

        if (!$user->is_active) {
            return response()->json([
                'message' => 'This demo account has been deactivated.',
            ], 403);
        }

        $user->update(['last_login_at' => now()]);
        $token = $user->createToken('demo-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'token' => $token,
            'user' => $user->load($this->userRelations($user)),
        ]);
    }

    /**
     * Request password reset — sends reset token via email.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();
        if (!$user) {
            // Don't reveal whether email exists
            return response()->json(['success' => true, 'message' => 'If that email exists, a reset link has been sent.']);
        }

        $resetToken = Str::random(64);
        $user->update([
            'password_reset_token' => hash('sha256', $resetToken),
            'password_reset_expires' => now()->addHours(2),
        ]);

        $frontendUrl = config('app.frontend_url', env('FRONTEND_URL', 'https://credentik.com'));
        $resetUrl = "{$frontendUrl}/#reset-password/{$resetToken}";

        Mail::to($user->email)->send(new PasswordResetMail($user, $resetUrl));

        return response()->json(['success' => true, 'message' => 'If that email exists, a reset link has been sent.']);
    }

    /**
     * Reset password using token.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string',
            'password' => ['required', 'string', 'min:8', 'confirmed', 'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^a-zA-Z\d]).+$/'],
        ]);

        $user = User::where('password_reset_token', hash('sha256', $request->token))
            ->where('password_reset_expires', '>', now())
            ->first();

        if (!$user) {
            throw ValidationException::withMessages([
                'token' => ['Invalid or expired reset token.'],
            ]);
        }

        $user->update([
            'password' => $request->password,
            'password_reset_token' => null,
            'password_reset_expires' => null,
        ]);

        return response()->json(['success' => true, 'message' => 'Password has been reset. You can now login.']);
    }

    /**
     * Accept invite — set password for invited user.
     */
    public function acceptInvite(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string',
            'password' => ['required', 'string', 'min:8', 'confirmed', 'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^a-zA-Z\d]).+$/'],
        ]);

        $user = User::where('invite_token', hash('sha256', $request->token))
            ->where('invite_expires', '>', now())
            ->whereNull('email_verified_at')
            ->first();

        if (!$user) {
            throw ValidationException::withMessages([
                'token' => ['Invalid or expired invitation.'],
            ]);
        }

        $user->update([
            'password' => $request->password,
            'invite_token' => null,
            'invite_expires' => null,
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'token' => $token,
            'user' => $user->load($this->userRelations($user)),
        ]);
    }

    /**
     * Seed demo users for testing — creates or updates demo accounts with known passwords.
     */
    public function seedDemoUsers(Request $request): JsonResponse
    {
        $agency = Agency::where('slug', 'ennhealth-psychiatry')->first();
        if (!$agency) {
            return response()->json(['success' => false, 'message' => 'Demo agency not found'], 404);
        }

        $org = \App\Models\Organization::where('agency_id', $agency->id)->first();
        $provider = \App\Models\Provider::where('agency_id', $agency->id)->first();

        $demoPassword = env('DEMO_USER_PASSWORD');
        if (!$demoPassword) {
            return response()->json(['success' => false, 'message' => 'DEMO_USER_PASSWORD env var is not set'], 500);
        }

        $accounts = [
            [
                'email' => 'owner@demo.credentik.com',
                'first_name' => 'Dana',
                'last_name' => 'Owner',
                'role' => 'owner',
                'agency_id' => $agency->id,
                'organization_id' => null,
                'provider_id' => null,
            ],
            [
                'email' => 'agency@demo.credentik.com',
                'first_name' => 'Alex',
                'last_name' => 'Agency',
                'role' => 'agency',
                'agency_id' => $agency->id,
                'organization_id' => null,
                'provider_id' => null,
            ],
            [
                'email' => 'org@demo.credentik.com',
                'first_name' => 'Olivia',
                'last_name' => 'Org',
                'role' => 'organization',
                'agency_id' => $agency->id,
                'organization_id' => $org?->id,
                'provider_id' => null,
            ],
            [
                'email' => 'provider@demo.credentik.com',
                'first_name' => 'Pat',
                'last_name' => 'Provider',
                'role' => 'provider',
                'agency_id' => $agency->id,
                'organization_id' => null,
                'provider_id' => $provider?->id,
            ],
        ];

        $results = [];
        foreach ($accounts as $data) {
            $user = User::updateOrCreate(
                ['email' => $data['email']],
                array_merge($data, [
                    'password' => $demoPassword,
                    'is_active' => true,
                    'invite_token' => null,
                    'invite_expires' => null,
                    'email_verified_at' => now(),
                ])
            );
            $results[] = "{$user->email} ({$user->role}) — " . ($user->wasRecentlyCreated ? 'created' : 'updated');
        }

        return response()->json([
            'success' => true,
            'message' => 'Demo users seeded',
            'accounts' => $results,
        ]);
    }

    /**
     * Determine which relationships to eager-load based on user role.
     */
    private function userRelations(User $user): array
    {
        // Superadmin is platform-level, may not have an agency
        if ($user->isSuperAdmin()) {
            return $user->agency_id ? ['agency.config'] : [];
        }

        $relations = ['agency.config'];

        if (in_array($user->role, ['organization', 'provider'])) {
            $relations[] = 'organization';
        }

        if ($user->role === 'provider') {
            $relations[] = 'provider';
        }

        return $relations;
    }
}
