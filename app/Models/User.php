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
     * Effective agency_id for a request — same as `$user->agency_id` for
     * normal users, but for a superadmin sending the `X-Agency-Id`
     * header (impersonation mode), returns the header value instead.
     *
     * Use this in controllers that pin queries to an agency:
     *
     *     Claim::where('agency_id', $request->user()->effectiveAgencyId($request))
     *
     * Without this, every controller hard-codes `$user->agency_id` which
     * means a superadmin (agency_id often null) sees zero rows. The
     * TenantScope global scope is one mechanism but controllers
     * historically distrust it; this helper lets the same controllers
     * stay strict while still honoring the impersonation header.
     */
    public function effectiveAgencyId(?\Illuminate\Http\Request $request = null): ?int
    {
        if ($this->isSuperAdmin() && $request) {
            $override = $request->header('X-Agency-Id');
            if ($override !== null && $override !== '') {
                return (int) $override;
            }
        }
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
