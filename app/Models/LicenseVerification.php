<?php

namespace App\Models;

use App\Models\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LicenseVerification extends Model
{
    use BelongsToAgency;

    protected $fillable = [
        'agency_id', 'license_id', 'provider_id', 'state', 'license_number',
        'verification_source', 'status', 'verified_at', 'source_data',
        'source_name', 'source_status', 'source_expiration', 'discrepancies',
        'pdf_url', 'verified_by',
    ];

    protected $casts = [
        'verified_at' => 'datetime',
        'source_data' => 'array',
        'source_expiration' => 'date',
    ];

    public function license(): BelongsTo { return $this->belongsTo(License::class); }
    public function provider(): BelongsTo { return $this->belongsTo(Provider::class); }
    public function verifiedBy(): BelongsTo { return $this->belongsTo(User::class, 'verified_by'); }
}
