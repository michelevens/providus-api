<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BoardCertificationType extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'slug', 'abbreviation', 'name', 'issuing_body', 'discipline',
        'specialty', 'recert_years', 'requires_cme', 'notes',
    ];

    protected $casts = ['requires_cme' => 'boolean'];
}
