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
                // Prefer the unified effectiveAgencyId helper so this
                // matches the read-side scoping logic. For impersonating
                // superadmins the helper returns the impersonated
                // tenant's id (from the Sanctum token ability or the
                // legacy X-Agency-Id header). For everyone else it
                // returns $user->agency_id.
                $model->agency_id = method_exists($user, 'effectiveAgencyId')
                    ? $user->effectiveAgencyId(request())
                    : ($user->role === 'superadmin'
                        ? (request()->header('X-Agency-Id') ?? $user->agency_id)
                        : $user->agency_id);
            }
        });
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }
}
