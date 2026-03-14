<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaxonomyCode extends Model
{
    public $timestamps = false;

    protected $fillable = ['code', 'type', 'specialty', 'classification'];
}
