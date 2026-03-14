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
                $model->agency_id = auth()->user()->agency_id;
            }
        });
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }
}
