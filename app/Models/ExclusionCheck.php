<?php

namespace App\Models;

use App\Models\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExclusionCheck extends Model
{
    use BelongsToAgency;

    protected $fillable = [
        'agency_id', 'provider_id', 'check_type', 'status', 'is_excluded',
        'checked_at', 'next_check_at', 'result_data', 'exclusion_type',
        'exclusion_date', 'reinstatement_date', 'waiver_state', 'notes', 'checked_by',
    ];

    protected $casts = [
        'is_excluded' => 'boolean',
        'checked_at' => 'datetime',
        'next_check_at' => 'datetime',
        'result_data' => 'array',
        'exclusion_date' => 'date',
        'reinstatement_date' => 'date',
    ];

    public function provider(): BelongsTo { return $this->belongsTo(Provider::class); }
    public function checkedBy(): BelongsTo { return $this->belongsTo(User::class, 'checked_by'); }
}
