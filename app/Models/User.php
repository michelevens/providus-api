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

    const ROLES = ['superadmin', 'agency', 'organization', 'provider'];
    const ROLE_HIERARCHY = [
        'superadmin' => 4,
        'agency' => 3,
        'organization' => 2,
        'provider' => 1,
    ];

    protected $fillable = [
        'agency_id', 'organization_id', 'provider_id',
        'email', 'password', 'first_name', 'last_name',
        'role', 'is_active', 'last_login_at',
        'invite_token', 'invite_expires',
        'password_reset_token', 'password_reset_expires',
        'email_verified_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
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

    public function isAgency(): bool
    {
        return in_array($this->role, ['superadmin', 'agency']);
    }

    public function isOrganization(): bool
    {
        return in_array($this->role, ['superadmin', 'agency', 'organization']);
    }

    public function isProvider(): bool
    {
        return in_array($this->role, ['superadmin', 'agency', 'organization', 'provider']);
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
        return $this->isSuperAdmin() || $this->role === 'agency';
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
