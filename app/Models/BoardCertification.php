<?php

namespace App\Models;

use App\Models\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BoardCertification extends Model
{
    use BelongsToAgency;

    protected $fillable = [
        'agency_id', 'provider_id', 'board_name', 'specialty',
        'certificate_number', 'initial_certification_date', 'expiration_date',
        'recertification_date', 'status', 'is_lifetime', 'is_verified', 'notes',
    ];

    protected $casts = [
        'initial_certification_date' => 'date',
        'expiration_date' => 'date',
        'recertification_date' => 'date',
        'is_lifetime' => 'boolean',
        'is_verified' => 'boolean',
    ];

    public function provider(): BelongsTo { return $this->belongsTo(Provider::class); }
}
