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
                }
                // No header = see everything
                return;
            }

            // All other roles are scoped to their agency
            $builder->where($model->getTable() . '.agency_id', $user->agency_id);
        }
    }
}
