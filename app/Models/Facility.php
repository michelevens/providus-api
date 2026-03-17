<?php

namespace App\Models;

use App\Models\Traits\Auditable;
use App\Models\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Facility extends Model
{
    use BelongsToAgency, SoftDeletes, Auditable;

    protected $fillable = [
        'agency_id', 'name', 'npi', 'facility_type', 'tax_id',
        'street', 'city', 'state', 'zip', 'phone', 'fax', 'email',
        'website', 'contact_name', 'contact_phone', 'contact_email',
        'is_active', 'notes',
    ];

    protected $casts = ['is_active' => 'boolean'];
}
