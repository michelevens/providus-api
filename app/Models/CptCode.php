<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CptCode extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'code', 'short_description', 'description', 'category', 'specialty_group',
        'avg_medicare_rate', 'time_unit', 'typical_minutes',
        'telehealth_eligible', 'is_active',
    ];

    protected $casts = [
        'avg_medicare_rate' => 'decimal:2',
        'telehealth_eligible' => 'boolean',
        'is_active' => 'boolean',
    ];
}
