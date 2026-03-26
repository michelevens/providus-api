<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (auth()->check()) {
            $user = auth()->user();

            // SuperAdmin can switch agencies via X-Agency-Id header
            if ($user->role === 'superadmin') {
                $overrideAgencyId = request()->header('X-Agency-Id');
                if ($overrideAgencyId) {
                    $builder->where($model->getTable() . '.agency_id', (int) $overrideAgencyId);
                } elseif (request()->is('api/admin/*')) {
                    // Admin endpoints: see everything (no filter)
                } else {
                    // Normal endpoints: scope to superadmin's own agency
                    $builder->where($model->getTable() . '.agency_id', $user->agency_id);
                }
                return;
            }

            // All other roles are scoped to their agency
            $builder->where($model->getTable() . '.agency_id', $user->agency_id);
        }
    }
}
