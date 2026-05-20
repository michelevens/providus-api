<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * StaffScope — apply per-staff organization filtering to a query.
 *
 * Background: agency, owner, and superadmin roles see ALL tenant data.
 * Staff role originally did too, but agencies wanted per-person scoping
 * (each staff handles a subset of client orgs). This helper centralizes
 * that filter so every list endpoint that resolves to an org applies
 * it consistently.
 *
 * Usage:
 *
 *     StaffScope::applyByOrganizationId($query, $user);
 *     // Or, when the column has a different name:
 *     StaffScope::apply($query, $user, 'organization_id');
 *
 * Semantics:
 *   - User->role !== 'staff' → no-op (agency/owner/superadmin see all).
 *   - User->role === 'staff' AND no rows in staff_organization_assignments
 *     → no-op (backward compat — unassigned staff sees everything).
 *   - User->role === 'staff' AND has assignments
 *     → query.whereIn($col, $assignedOrgIds).
 *
 * For tables that don't have an organization_id column directly (e.g.,
 * claims have provider_id which has organization_id), use one of the
 * preset helpers (applyToClaims, applyToDenials, etc.) below — they
 * encode the right join/exists pattern per table.
 */
class StaffScope
{
    /**
     * Apply org-scope filter on an arbitrary column.
     */
    public static function apply(Builder $query, ?User $user, string $orgIdColumn = 'organization_id'): Builder
    {
        $orgIds = $user?->assignedOrgIds();
        if ($orgIds === null) return $query;
        return $query->whereIn($orgIdColumn, $orgIds);
    }

    /**
     * Convenience: filter on the organizations table directly.
     */
    public static function applyToOrganizations(Builder $query, ?User $user): Builder
    {
        $orgIds = $user?->assignedOrgIds();
        if ($orgIds === null) return $query;
        return $query->whereIn('id', $orgIds);
    }

    /**
     * Claims don't carry organization_id — they belong to a provider,
     * which belongs to an organization. Filter via the provider join.
     * NULL provider_id rows (rare, but exist for unattributed imports)
     * are excluded when staff is scoped (the operator can't see what
     * they can't tie to an org).
     */
    public static function applyToClaims(Builder $query, ?User $user): Builder
    {
        $orgIds = $user?->assignedOrgIds();
        if ($orgIds === null) return $query;
        return $query->whereHas('provider', fn ($q) => $q->whereIn('organization_id', $orgIds));
    }

    /**
     * Denials inherit scope from their parent claim.
     */
    public static function applyToDenials(Builder $query, ?User $user): Builder
    {
        $orgIds = $user?->assignedOrgIds();
        if ($orgIds === null) return $query;
        return $query->whereHas('claim.provider', fn ($q) => $q->whereIn('organization_id', $orgIds));
    }
}
