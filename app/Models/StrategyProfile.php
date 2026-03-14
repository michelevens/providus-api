<?php

namespace App\Models;

use App\Models\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;

class StrategyProfile extends Model
{
    use BelongsToAgency;

    protected $fillable = [
        'agency_id', 'slug', 'name', 'description',
        'target_states', 'wave_rules', 'revenue_threshold',
        'auto_wave_assignment', 'is_default',
    ];

    protected $casts = [
        'target_states' => 'array',
        'wave_rules' => 'array',
        'revenue_threshold' => 'decimal:2',
        'auto_wave_assignment' => 'boolean',
        'is_default' => 'boolean',
    ];
}
