<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TwoFactorController extends Controller
{
    /**
     * Get 2FA status for current user.
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        return response()->json([
            'success' => true,
            'data' => [
                'enabled' => (bool) $user->two_factor_enabled,
                'confirmed_at' => $user->two_factor_confirmed_at,
            ],
        ]);
    }

    /**
     * Enable 2FA — generates secret and returns QR code provisioning URI.
     */
    public function enable(Request $request): JsonResponse
    {
        $request->validate(['password' => 'required|string']);

        $user = $request->user();

        if (!Hash::check($request->password, $user->password)) {
            return response()->json(['success' => false, 'message' => 'Incorrect password'], 403);
        }

        // Generate TOTP secret (base32, 20 bytes = 32 chars)
        $secret = $this->generateBase32Secret();

        // Plaintext shown to user ONCE; we store only bcrypt hashes.
        [$plainCodes, $hashedCodes] = $this->generateRecoveryCodes();

        $user->update([
            'two_factor_secret' => Crypt::encryptString($secret),
            'two_factor_recovery_codes' => Crypt::encryptString(json_encode($hashedCodes)),
            'two_factor_enabled' => false, // Not confirmed yet
            'two_factor_confirmed_at' => null,
        ]);

        // Build otpauth URI for QR code
        $issuer = urlencode('Credentik');
        $email = urlencode($user->email);
        $otpauthUrl = "otpauth://totp/{$issuer}:{$email}?secret={$secret}&issuer={$issuer}&digits=6&period=30";

        return response()->json([
            'success' => true,
            'data' => [
                'secret' => $secret,
                'otpauth_url' => $otpauthUrl,
                'recovery_codes' => $plainCodes,
            ],
            'message' => 'Save the recovery codes now — they will not be shown again.',
        ]);
    }

    /**
     * Verify TOTP code and confirm 2FA setup.
     */
    public function verify(Request $request): JsonResponse
    {
        $request->validate(['code' => 'required|string|size:6']);

        $user = $request->user();

        if (!$user->two_factor_secret) {
            return response()->json(['success' => false, 'message' => '2FA not initialized. Call /2fa/enable first.'], 400);
        }

        $secret = Crypt::decryptString($user->two_factor_secret);

        if (!$this->verifyTotp($secret, $request->code)) {
            return response()->json(['success' => false, 'message' => 'Invalid verification code'], 422);
        }

        $user->update([
            'two_factor_enabled' => true,
            'two_factor_confirmed_at' => now(),
        ]);

        return response()->json(['success' => true, 'message' => 'Two-factor authentication enabled']);
    }

    /**
     * Disable 2FA.
     */
    public function disable(Request $request): JsonResponse
    {
        $request->validate(['password' => 'required|string']);

        $user = $request->user();

        if (!Hash::check($request->password, $user->password)) {
            return response()->json(['success' => false, 'message' => 'Incorrect password'], 403);
        }

        $user->update([
            'two_factor_enabled' => false,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ]);

        return response()->json(['success' => true, 'message' => 'Two-factor authentication disabled']);
    }

    /**
     * Regenerate recovery codes — returns the new plaintext set ONCE; only hashes are stored.
     */
    public function regenerateRecoveryCodes(Request $request): JsonResponse
    {
        $request->validate(['password' => 'required|string']);

        $user = $request->user();

        if (!Hash::check($request->password, $user->password)) {
            return response()->json(['success' => false, 'message' => 'Incorrect password'], 403);
        }

        [$plainCodes, $hashedCodes] = $this->generateRecoveryCodes();

        $user->update([
            'two_factor_recovery_codes' => Crypt::encryptString(json_encode($hashedCodes)),
        ]);

        return response()->json([
            'success' => true,
            'data' => ['recovery_codes' => $plainCodes],
            'message' => 'Save the recovery codes now — they will not be shown again.',
        ]);
    }

    /**
     * Verify 2FA during login (public endpoint, uses cached short-lived session token).
     */
    public function verifyLogin(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|integer',
            'code' => 'required|string',
            'login_token' => 'required|string',
        ]);

        $cacheKey = '2fa_session:' . hash('sha256', $request->login_token);
        $session = Cache::get($cacheKey);

        if (!$session || ($session['user_id'] ?? null) !== (int) $request->user_id) {
            return response()->json(['success' => false, 'message' => 'Invalid or expired login session'], 403);
        }

        $user = User::findOrFail($request->user_id);

        $code = $request->code;
        $secret = Crypt::decryptString($user->two_factor_secret);

        // Try TOTP first
        if (strlen($code) === 6 && $this->verifyTotp($secret, $code)) {
            Cache::forget($cacheKey);
            return $this->issueToken($user);
        }

        // Try recovery code — codes are bcrypt hashes; Hash::check is constant-time.
        $hashedCodes = json_decode(Crypt::decryptString($user->two_factor_recovery_codes), true) ?? [];
        foreach ($hashedCodes as $i => $hash) {
            if (Hash::check($code, $hash)) {
                unset($hashedCodes[$i]);
                $user->update([
                    'two_factor_recovery_codes' => Crypt::encryptString(json_encode(array_values($hashedCodes))),
                ]);
                Cache::forget($cacheKey);
                return $this->issueToken($user);
            }
        }

        return response()->json(['success' => false, 'message' => 'Invalid verification code'], 422);
    }

    private function issueToken(User $user): JsonResponse
    {
        $user->update(['last_login_at' => now()]);
        $token = $user->createToken('auth-token')->plainTextToken;

        $relations = [];
        if ($user->agency_id) $relations[] = 'agency.config';
        if (in_array($user->role, ['organization', 'provider'])) $relations[] = 'organization';
        if ($user->role === 'provider') $relations[] = 'provider';

        return response()->json([
            'success' => true,
            'token' => $token,
            'user' => $user->load($relations),
        ]);
    }

    /**
     * Returns [plaintextCodes, hashedCodes] — plaintext is shown to the user once,
     * hashes are persisted so verification is one-way.
     */
    private function generateRecoveryCodes(int $count = 8, int $length = 10): array
    {
        $plain = collect(range(1, $count))->map(fn() => Str::random($length))->all();
        $hashed = array_map(fn($c) => Hash::make($c), $plain);
        return [$plain, $hashed];
    }

    // ─── TOTP Helpers ───

    private function generateBase32Secret(int $length = 20): string
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        for ($i = 0; $i < 32; $i++) {
            $secret .= $chars[random_int(0, 31)];
        }
        return $secret;
    }

    private function verifyTotp(string $secret, string $code, int $window = 1): bool
    {
        $timeSlice = (int) floor(time() / 30);
        for ($i = -$window; $i <= $window; $i++) {
            if (hash_equals($this->generateTotp($secret, $timeSlice + $i), $code)) {
                return true;
            }
        }
        return false;
    }

    private function generateTotp(string $secret, int $timeSlice): string
    {
        $key = $this->base32Decode($secret);
        $time = pack('N*', 0, $timeSlice);
        $hmac = hash_hmac('sha1', $time, $key, true);
        $offset = ord($hmac[19]) & 0x0F;
        $code = (
            ((ord($hmac[$offset]) & 0x7F) << 24) |
            ((ord($hmac[$offset + 1]) & 0xFF) << 16) |
            ((ord($hmac[$offset + 2]) & 0xFF) << 8) |
            (ord($hmac[$offset + 3]) & 0xFF)
        ) % 1000000;
        return str_pad((string) $code, 6, '0', STR_PAD_LEFT);
    }

    private function base32Decode(string $input): string
    {
        $map = array_flip(str_split('ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'));
        $input = strtoupper(rtrim($input, '='));
        $buffer = 0;
        $bitsLeft = 0;
        $output = '';
        for ($i = 0, $len = strlen($input); $i < $len; $i++) {
            $buffer = ($buffer << 5) | ($map[$input[$i]] ?? 0);
            $bitsLeft += 5;
            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $output .= chr(($buffer >> $bitsLeft) & 0xFF);
            }
        }
        return $output;
    }
}
