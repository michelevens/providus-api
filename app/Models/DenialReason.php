<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DenialReason extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'slug', 'name', 'category', 'description',
        'recommended_action', 'is_resubmittable', 'sort_order',
    ];

    protected $casts = ['is_resubmittable' => 'boolean'];
}
