<?php

namespace App\Models;

use App\Models\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\SoftDeletes;

class ProviderWorkHistory extends Model
{
    use BelongsToAgency, SoftDeletes;

    protected $table = 'provider_work_history';

    protected $fillable = [
        'agency_id', 'provider_id', 'employer_name', 'position_title',
        'department', 'start_date', 'end_date', 'is_current',
        'city', 'state', 'supervisor_name', 'supervisor_phone',
        'reason_for_leaving', 'is_verified', 'notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_current' => 'boolean',
        'is_verified' => 'boolean',
    ];

    public function provider(): BelongsTo { return $this->belongsTo(Provider::class); }
}
