<?php

namespace App\Models;

use App\Models\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use Auditable, HasApiTokens, HasFactory, Notifiable;

    /**
     * Auditable strips these before writing to audit_logs:
     * - password (already hashed, but logging hashes is pointless
     *   noise and would let a compromised audit_logs reader run
     *   offline bcrypt attacks)
     * - remember_token / reset / invite tokens — short-lived secrets
     * - 2FA secret + recovery codes — encrypted at rest but ditto
     * - last_login_at — flips on every sign-in, would flood the
     *   log with thousands of no-signal rows
     * - email_verified_at, two_factor_confirmed_at — likewise
     *   low-signal lifecycle flags
     *
     * Things that DO get audited on User: role changes, email/name
     * changes, is_active toggles, agency_id moves (tenant
     * reassignments), 2FA enabled flag flip. These are the
     * security-relevant events compliance auditors care about.
     */
    protected array $auditExclude = [
        'password', 'remember_token',
        'password_reset_token', 'password_reset_expires',
        'invite_token', 'invite_expires',
        'two_factor_secret', 'two_factor_recovery_codes',
        'last_login_at', 'email_verified_at', 'two_factor_confirmed_at',
        'updated_at',
    ];

    const ROLES = ['superadmin', 'owner', 'agency', 'staff', 'organization', 'provider'];

    // Role levels for the EnsureAgencyRole middleware. Higher level
    // grants strictly more access. A middleware that gates on `role:X`
    // admits the user if their level >= X's level.
    //
    // Where 'staff' fits: between agency and organization. Staff is a
    // day-to-day worker (credentialing specialist, biller) inside an
    // agency. Scoped to the same agency as agency-owners (TenantScope
    // treats them identically — agency_id filter only, no org/provider
    // sub-scope), but the role:agency-gated routes (user management,
    // agency settings, audit logs, auth events) BLOCK them.
    //
    // Why 2 (and not 2.5): integer levels because ROLE_HIERARCHY is
    // used as the source-of-truth for comparisons; fractional values
    // work but read confusingly. Organization is bumped to 1, provider
    // to 0 (still lowest), to keep ordering correct.
    //
    // Backward compat: pre-2026-05-16, ui_role=staff existed but role
    // was stored as 'agency'. A backfill on this same commit corrects
    // those 5 users; new invites with role=staff via the existing
    // inviteUser flow store it correctly going forward.
    const ROLE_HIERARCHY = [
        'superadmin'   => 5,
        'owner'        => 4,
        'agency'       => 3,
        'staff'        => 2,
        'organization' => 1,
        'provider'     => 0,
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

    /**
     * Per-staff organization assignments. Many-to-many via
     * staff_organization_assignments. Used to scope what a staff user
     * can SEE — providers, applications, claims, etc. tied to
     * organizations outside this set are hidden from them.
     *
     * Semantics:
     *   - Empty set = no restriction (sees all the agency's orgs).
     *     Backward compatible — existing staff with no rows keep their
     *     current "sees everything" behavior.
     *   - Non-empty set = restricted to those orgs only.
     *
     * Agency/owner/superadmin ignore this entirely (they always see all
     * agency data). Org/provider users have their own dedicated scope
     * via the organization_id / provider_id columns on this table.
     */
    public function assignedOrganizations(): BelongsToMany
    {
        return $this->belongsToMany(
            Organization::class,
            'staff_organization_assignments',
            'user_id',
            'organization_id',
        )->withTimestamps()->withPivot(['agency_id', 'assigned_by']);
    }

    /**
     * Returns the array of organization_ids this staff user is scoped
     * to, OR null if they have no assignments (= see everything).
     * Controllers use the null-coalesce check pattern:
     *
     *     $scope = $user->assignedOrgIds();
     *     if ($scope !== null) $query->whereIn('organization_id', $scope);
     *
     * Only applies to role=staff. Higher roles always return null
     * (sees everything). Org/provider roles are scoped through their
     * direct FK and never go through this path.
     */
    public function assignedOrgIds(): ?array
    {
        if ($this->role !== 'staff') {
            return null;
        }
        $ids = $this->assignedOrganizations()->pluck('organizations.id')->all();
        return empty($ids) ? null : $ids;
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

    // Naming convention: each is<Role>() returns true when the user
    // has AT LEAST that role's level in the hierarchy — i.e. that
    // role's privileges OR higher. NOT an exact-role check.

    public function isAgency(): bool
    {
        return in_array($this->role, ['superadmin', 'owner', 'agency']);
    }

    public function isOrganization(): bool
    {
        // staff sits ABOVE organization in the hierarchy (level 2 vs 1)
        // so staff users qualify for organization-level access.
        return in_array($this->role, ['superadmin', 'owner', 'agency', 'staff', 'organization']);
    }

    public function isProvider(): bool
    {
        // Provider is the lowest level — every authenticated role qualifies.
        return in_array($this->role, ['superadmin', 'owner', 'agency', 'staff', 'organization', 'provider']);
    }

    /**
     * Check if this user's role is at least the given minimum role.
     */
    public function hasMinimumRole(string $role): bool
    {
        // Fallback to -1 (below the lowest real role, provider=0) so a
        // user with an unknown role NEVER passes. Pre-2026-05-16 this
        // was `?? 0`, which was ambiguous after provider was renumbered
        // to 0 in the hierarchy.
        return (self::ROLE_HIERARCHY[$this->role] ?? -1) >= (self::ROLE_HIERARCHY[$role] ?? 99);
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

    /**
     * True when the user has AT LEAST staff-level privileges.
     * As of 2026-05-16 staff is a real backend role (hierarchy level 2),
     * so this is anyone agency-or-above OR the user with role='staff'.
     * Previously a back-compat alias for isOrganization() — corrected
     * because that was overly permissive (allowed organization-role
     * users to pass "isStaff" checks).
     */
    public function isStaff(): bool
    {
        return in_array($this->role, ['superadmin', 'owner', 'agency', 'staff']);
    }

    // ── Accessors ────────────────────────────────────────────────

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }
}
