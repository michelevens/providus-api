<?php

namespace App\Models;

use App\Models\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationContact extends Model
{
    use BelongsToAgency;

    protected $fillable = [
        'agency_id', 'organization_id', 'name', 'title', 'role',
        'email', 'phone', 'notes',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
