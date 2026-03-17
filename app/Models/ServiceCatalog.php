<?php

namespace App\Models;

use App\Models\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;

class ServiceCatalog extends Model
{
    use BelongsToAgency;

    protected $table = 'service_catalog';

    protected $fillable = [
        'agency_id', 'name', 'code', 'description', 'category',
        'default_price', 'is_active',
    ];

    protected $casts = [
        'default_price' => 'decimal:2',
        'is_active' => 'boolean',
    ];
}
