<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LicenseType extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'slug', 'abbreviation', 'name', 'discipline', 'education_requirement',
        'requires_supervision', 'can_prescribe', 'is_independent', 'scope_notes', 'sort_order',
    ];

    protected $casts = [
        'requires_supervision' => 'boolean',
        'can_prescribe' => 'boolean',
        'is_independent' => 'boolean',
    ];
}
