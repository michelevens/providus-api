<?php

namespace App\Models;

use App\Models\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaqhTracking extends Model
{
    use BelongsToAgency;

    protected $table = 'caqh_tracking';

    protected $fillable = [
        'agency_id', 'provider_id', 'caqh_id', 'profile_status',
        'profile_status_date', 'roster_status', 'attestation_date',
        'attestation_expires', 'next_attestation', 'last_checked_at', 'error',
    ];

    protected $casts = [
        'profile_status_date' => 'date',
        'attestation_date' => 'date',
        'attestation_expires' => 'date',
        'next_attestation' => 'date',
        'last_checked_at' => 'datetime',
    ];

    public function provider(): BelongsTo { return $this->belongsTo(Provider::class); }
}
