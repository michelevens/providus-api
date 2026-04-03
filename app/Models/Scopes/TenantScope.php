<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Schema;

class TenantScope implements Scope
{
    /** Cache column existence checks to avoid repeated DB queries */
    private static array $columnCache = [];

    private static function hasCol(string $table, string $column): bool
    {
        $key = "{$table}.{$column}";
        if (!isset(self::$columnCache[$key])) {
            self::$columnCache[$key] = self::hasCol($table, $column);
        }
        return self::$columnCache[$key];
    }

    public function apply(Builder $builder, Model $model): void
    {
        if (!auth()->check()) return;

        $user = auth()->user();
        $table = $model->getTable();

        // SuperAdmin can switch agencies via X-Agency-Id header
        if ($user->role === 'superadmin') {
            $overrideAgencyId = request()->header('X-Agency-Id');
            if ($overrideAgencyId) {
                $builder->where("{$table}.agency_id", (int) $overrideAgencyId);
            }
            // No header = see everything (original behavior)
            return;
        }

        // All other roles are scoped to their agency
        $builder->where("{$table}.agency_id", $user->agency_id);

        // ── Organization / Provider scope (within agency) ──
        // Enforced for organization/provider role users AND when frontend sends scope headers
        $scopeType = request()->header('X-Scope-Type', '');
        $scopeOrgId = request()->header('X-Scope-Org-Id', '');
        $scopeProviderId = request()->header('X-Scope-Provider-Id', '');

        // Organization role users are always scoped to their org
        if ($user->role === 'organization' && $user->organization_id) {
            $scopeType = 'organization';
            $scopeOrgId = $user->organization_id;
        }

        // Provider role users are always scoped to their provider
        if ($user->role === 'provider' && $user->provider_id) {
            $scopeType = 'provider';
            $scopeProviderId = $user->provider_id;
        }

        // Apply organization scope
        if ($scopeType === 'organization' && $scopeOrgId) {
            $orgId = (int) $scopeOrgId;
            $builder->where(function (Builder $q) use ($table, $orgId, $model) {
                $applied = false;

                // Direct organization_id column
                if (self::hasCol($table, 'organization_id')) {
                    $q->where("{$table}.organization_id", $orgId);
                    $applied = true;
                }

                // billing_client_id → maps to organization via billing_clients table
                if (self::hasCol($table, 'billing_client_id')) {
                    if ($applied) {
                        $q->orWhereIn("{$table}.billing_client_id", function ($sub) use ($orgId) {
                            $sub->select('id')->from('billing_clients')->where('organization_id', $orgId);
                        });
                    } else {
                        $q->whereIn("{$table}.billing_client_id", function ($sub) use ($orgId) {
                            $sub->select('id')->from('billing_clients')->where('organization_id', $orgId);
                        });
                        $applied = true;
                    }
                }

                // provider_id → maps to organization via providers table
                if (!$applied && self::hasCol($table, 'provider_id')) {
                    $q->whereIn("{$table}.provider_id", function ($sub) use ($orgId) {
                        $sub->select('id')->from('providers')->where('organization_id', $orgId);
                    });
                    $applied = true;
                }

                // If the table IS organizations, match by id
                if ($table === 'organizations') {
                    if ($applied) $q->orWhere("{$table}.id", $orgId);
                    else $q->where("{$table}.id", $orgId);
                }

                // If the table IS providers, match by organization_id
                if ($table === 'providers' && !$applied) {
                    $q->where("{$table}.organization_id", $orgId);
                }
            });
        }

        // Apply provider scope
        if ($scopeType === 'provider' && $scopeProviderId) {
            $provId = (int) $scopeProviderId;
            $builder->where(function (Builder $q) use ($table, $provId) {
                $applied = false;

                if (self::hasCol($table, 'provider_id')) {
                    $q->where("{$table}.provider_id", $provId);
                    $applied = true;
                }

                if (self::hasCol($table, 'rendering_provider_id')) {
                    if ($applied) $q->orWhere("{$table}.rendering_provider_id", $provId);
                    else { $q->where("{$table}.rendering_provider_id", $provId); $applied = true; }
                }

                // If the table IS providers, match by id
                if ($table === 'providers') {
                    if ($applied) $q->orWhere("{$table}.id", $provId);
                    else $q->where("{$table}.id", $provId);
                }
            });
        }
    }
}
