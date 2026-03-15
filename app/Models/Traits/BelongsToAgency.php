<?php

namespace App\Models\Traits;

use App\Models\Agency;
use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToAgency
{
    protected static function bootBelongsToAgency(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model) {
            if (auth()->check() && !$model->agency_id) {
                $user = auth()->user();
                // SuperAdmin must use X-Agency-Id header to scope creates
                if ($user->role === 'superadmin') {
                    $model->agency_id = request()->header('X-Agency-Id') ?? $user->agency_id;
                } else {
                    $model->agency_id = $user->agency_id;
                }
            }
        });
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }
}
