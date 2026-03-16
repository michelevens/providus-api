<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InsurancePlanType extends Model
{
    public $timestamps = false;

    protected $fillable = ['slug', 'name', 'description', 'sort_order'];
}
