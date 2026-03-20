<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        // Generate recovery codes
        $recoveryCodes = collect(range(1, 8))->map(fn() => Str::random(10))->all();

        $user->update([
            'two_factor_secret' => Crypt::encryptString($secret),
            'two_factor_recovery_codes' => Crypt::encryptString(json_encode($recoveryCodes)),
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
                'recovery_codes' => $recoveryCodes,
            ],
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
     * Get recovery codes (requires password).
     */
    public function recoveryCodes(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->two_factor_enabled || !$user->two_factor_recovery_codes) {
            return response()->json(['success' => false, 'message' => '2FA is not enabled'], 400);
        }

        $codes = json_decode(Crypt::decryptString($user->two_factor_recovery_codes), true);

        return response()->json(['success' => true, 'data' => ['recovery_codes' => $codes]]);
    }

    /**
     * Regenerate recovery codes.
     */
    public function regenerateRecoveryCodes(Request $request): JsonResponse
    {
        $request->validate(['password' => 'required|string']);

        $user = $request->user();

        if (!Hash::check($request->password, $user->password)) {
            return response()->json(['success' => false, 'message' => 'Incorrect password'], 403);
        }

        $recoveryCodes = collect(range(1, 8))->map(fn() => Str::random(10))->all();

        $user->update([
            'two_factor_recovery_codes' => Crypt::encryptString(json_encode($recoveryCodes)),
        ]);

        return response()->json(['success' => true, 'data' => ['recovery_codes' => $recoveryCodes]]);
    }

    /**
     * Verify 2FA during login (public endpoint, uses temporary token).
     */
    public function verifyLogin(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|integer',
            'code' => 'required|string',
            'login_token' => 'required|string',
        ]);

        $user = User::findOrFail($request->user_id);

        // Verify the temporary login token (hash of user email + a timestamp window)
        $validToken = hash('sha256', $user->email . now()->format('Y-m-d-H'));
        $validTokenPrev = hash('sha256', $user->email . now()->subHour()->format('Y-m-d-H'));

        if ($request->login_token !== $validToken && $request->login_token !== $validTokenPrev) {
            return response()->json(['success' => false, 'message' => 'Invalid login session'], 403);
        }

        $code = $request->code;
        $secret = Crypt::decryptString($user->two_factor_secret);

        // Try TOTP first
        if (strlen($code) === 6 && $this->verifyTotp($secret, $code)) {
            return $this->issueToken($user);
        }

        // Try recovery code
        $recoveryCodes = json_decode(Crypt::decryptString($user->two_factor_recovery_codes), true);
        if (in_array($code, $recoveryCodes)) {
            // Remove used recovery code
            $recoveryCodes = array_values(array_filter($recoveryCodes, fn($c) => $c !== $code));
            $user->update([
                'two_factor_recovery_codes' => Crypt::encryptString(json_encode($recoveryCodes)),
            ]);
            return $this->issueToken($user);
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
            if ($this->generateTotp($secret, $timeSlice + $i) === $code) {
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
