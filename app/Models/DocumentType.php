<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentType extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'slug', 'name', 'category', 'description',
        'is_required', 'has_expiration', 'typical_validity_months', 'sort_order',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'has_expiration' => 'boolean',
    ];
}
