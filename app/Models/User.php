<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    const ROLES = ['superadmin', 'owner', 'agency', 'organization', 'provider'];
    const ROLE_HIERARCHY = [
        'superadmin' => 5,
        'owner' => 4,
        'agency' => 3,
        'organization' => 2,
        'provider' => 1,
    ];

    protected $fillable = [
        'agency_id', 'organization_id', 'provider_id',
        'email', 'password', 'first_name', 'last_name',
        'role', 'ui_role', 'is_active', 'last_login_at',
        'invite_token', 'invite_expires',
        'password_reset_token', 'password_reset_expires',
        'email_verified_at',
        'two_factor_enabled', 'two_factor_secret',
        'two_factor_recovery_codes', 'two_factor_confirmed_at',
    ];

    protected $hidden = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'two_factor_enabled' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    // ── Relationships ────────────────────────────────────────────

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    // ── New Role Helpers ─────────────────────────────────────────

    public function isSuperAdmin(): bool
    {
        return $this->role === 'superadmin';
    }

    /**
     * Effective agency_id for a request — the actual tenant whose data
     * the request should see. Resolved in this order:
     *
     *   1. If the request is authenticated with a token that carries an
     *      `impersonate:<id>` ability, return <id>. This is the modern
     *      path — impersonation token minted by /admin/agencies/{id}/impersonate
     *      is the only thing that grants the ability, and it expires in
     *      2 hours.
     *   2. If the user is a superadmin AND the legacy `X-Agency-Id` header
     *      is set, return that. Kept for backward compatibility with the
     *      header-based impersonation flow shipped earlier; remove after
     *      all V2 sessions have migrated to the token-based flow.
     *   3. Otherwise return $this->agency_id.
     *
     * Use this in controllers that pin queries to an agency:
     *
     *     Claim::where('agency_id', $request->user()->effectiveAgencyId($request))
     */
    public function effectiveAgencyId(?\Illuminate\Http\Request $request = null): ?int
    {
        // (1) Token ability — server-side bounded, the secure path.
        // $token->abilities is a JSON-cast array on PersonalAccessToken;
        // we access it directly (no method_exists guard — that was a
        // bug, PersonalAccessToken has the property but not the method).
        try {
            $token = $this->currentAccessToken();
            if ($token) {
                $abilities = $token->abilities ?? null;
                if (is_array($abilities)) {
                    foreach ($abilities as $ability) {
                        if (is_string($ability) && str_starts_with($ability, 'impersonate:')) {
                            return (int) substr($ability, strlen('impersonate:'));
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            // currentAccessToken() can throw if Sanctum isn't bootstrapped
            // (e.g. running in a worker context). Fall through to the
            // header / default paths.
        }
        // (2) Legacy header — superadmins only.
        if ($this->isSuperAdmin() && $request) {
            $override = $request->header('X-Agency-Id');
            if ($override !== null && $override !== '') {
                return (int) $override;
            }
        }
        // (3) Default.
        return $this->agency_id;
    }

    public function isAgency(): bool
    {
        return in_array($this->role, ['superadmin', 'owner', 'agency']);
    }

    public function isOrganization(): bool
    {
        return in_array($this->role, ['superadmin', 'owner', 'agency', 'organization']);
    }

    public function isProvider(): bool
    {
        return in_array($this->role, ['superadmin', 'owner', 'agency', 'organization', 'provider']);
    }

    /**
     * Check if this user's role is at least the given minimum role.
     */
    public function hasMinimumRole(string $role): bool
    {
        return (self::ROLE_HIERARCHY[$this->role] ?? 0) >= (self::ROLE_HIERARCHY[$role] ?? 99);
    }

    // ── Backward-Compatible Helpers (transition period) ──────────

    public function isOwner(): bool
    {
        return $this->isSuperAdmin() || in_array($this->role, ['owner', 'agency']);
    }

    public function isAdmin(): bool
    {
        return $this->isAgency();
    }

    public function isStaff(): bool
    {
        return $this->isOrganization();
    }

    // ── Accessors ────────────────────────────────────────────────

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }
}
