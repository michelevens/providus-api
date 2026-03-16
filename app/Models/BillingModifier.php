<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillingModifier extends Model
{
    public $timestamps = false;

    protected $fillable = ['code', 'name', 'description', 'category', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];
}
