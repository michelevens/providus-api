<?php

namespace App\Models;

use App\Models\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class License extends Model
{
    use BelongsToAgency;

    protected $fillable = [
        'agency_id', 'provider_id', 'state', 'license_number', 'license_type',
        'status', 'issue_date', 'expiration_date', 'renewal_date',
        'compact_state', 'notes',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'expiration_date' => 'date',
        'renewal_date' => 'date',
        'compact_state' => 'boolean',
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
