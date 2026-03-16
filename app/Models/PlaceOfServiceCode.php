<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlaceOfServiceCode extends Model
{
    public $timestamps = false;

    protected $fillable = ['code', 'name', 'description', 'is_facility', 'is_active'];

    protected $casts = [
        'is_facility' => 'boolean',
        'is_active' => 'boolean',
    ];
}
