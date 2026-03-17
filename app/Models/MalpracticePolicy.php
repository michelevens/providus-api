<?php

namespace App\Models;

use App\Models\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\SoftDeletes;

class MalpracticePolicy extends Model
{
    use BelongsToAgency, SoftDeletes;

    protected $fillable = [
        'agency_id', 'provider_id', 'carrier_name', 'policy_number',
        'coverage_type', 'per_incident_amount', 'aggregate_amount',
        'effective_date', 'expiration_date', 'status', 'has_tail_coverage',
        'has_claims_history', 'claims_count', 'notes',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'expiration_date' => 'date',
        'per_incident_amount' => 'decimal:2',
        'aggregate_amount' => 'decimal:2',
        'has_tail_coverage' => 'boolean',
        'has_claims_history' => 'boolean',
    ];

    public function provider(): BelongsTo { return $this->belongsTo(Provider::class); }
}
