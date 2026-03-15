<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
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
            'password' => 'required|string|min:8|confirmed',
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

        $user->update(['last_login_at' => now()]);
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

        $frontendUrl = config('app.frontend_url', env('FRONTEND_URL', 'https://app.credentik.com'));
        $resetUrl = "{$frontendUrl}/#reset-password/{$resetToken}";

        Mail::raw(
            "You requested a password reset for your Credentik account.\n\nClick here to reset your password:\n{$resetUrl}\n\nThis link expires in 2 hours.\n\nIf you didn't request this, ignore this email.",
            function ($message) use ($user) {
                $message->to($user->email)
                    ->subject('Credentik — Reset Your Password');
            }
        );

        return response()->json(['success' => true, 'message' => 'If that email exists, a reset link has been sent.']);
    }

    /**
     * Reset password using token.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
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
            'password' => 'required|string|min:8|confirmed',
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
