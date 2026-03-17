<?php

namespace App\Models;

use App\Models\Traits\Auditable;
use App\Models\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\SoftDeletes;

class DeaRegistration extends Model
{
    use BelongsToAgency, SoftDeletes, Auditable;

    protected $fillable = [
        'agency_id', 'provider_id', 'dea_number', 'schedules', 'state',
        'business_activity', 'drug_category', 'status', 'expiration_date',
        'verified_at', 'source_data', 'notes',
    ];

    protected $casts = [
        'schedules' => 'array',
        'source_data' => 'array',
        'expiration_date' => 'date',
        'verified_at' => 'datetime',
    ];

    public function provider(): BelongsTo { return $this->belongsTo(Provider::class); }

    public function isExpired(): bool
    {
        return $this->expiration_date && $this->expiration_date->isPast();
    }

    public function isExpiringSoon(int $days = 90): bool
    {
        return $this->expiration_date && $this->expiration_date->isBetween(now(), now()->addDays($days));
    }
}
