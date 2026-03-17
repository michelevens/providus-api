<?php

namespace App\Models;

use App\Models\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\SoftDeletes;

class ProviderCme extends Model
{
    use BelongsToAgency, SoftDeletes;

    protected $table = 'provider_cme';

    protected $fillable = [
        'agency_id', 'provider_id', 'course_name', 'provider_org',
        'credit_hours', 'credit_type', 'completion_date', 'expiration_date',
        'certificate_number', 'is_verified', 'notes',
    ];

    protected $casts = [
        'credit_hours' => 'decimal:2',
        'completion_date' => 'date',
        'expiration_date' => 'date',
        'is_verified' => 'boolean',
    ];

    public function provider(): BelongsTo { return $this->belongsTo(Provider::class); }
}
