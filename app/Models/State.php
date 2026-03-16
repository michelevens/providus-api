<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class State extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'code', 'name', 'region', 'population',
        'is_compact_nlc', 'is_compact_psypact', 'is_compact_aslp',
        'is_compact_pt', 'is_compact_ot', 'is_compact_counseling',
    ];

    protected $casts = [
        'is_compact_nlc' => 'boolean',
        'is_compact_psypact' => 'boolean',
        'is_compact_aslp' => 'boolean',
        'is_compact_pt' => 'boolean',
        'is_compact_ot' => 'boolean',
        'is_compact_counseling' => 'boolean',
    ];
}
