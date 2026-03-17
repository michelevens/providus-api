<?php

namespace App\Models;

use App\Models\Traits\Auditable;
use App\Models\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Organization extends Model
{
    use BelongsToAgency, SoftDeletes, Auditable;

    protected $fillable = [
        'agency_id', 'legacy_id', 'name', 'npi', 'tax_id',
        'address_street', 'address_city', 'address_state', 'address_zip',
        'phone', 'email', 'taxonomy',
    ];

    public function providers(): HasMany { return $this->hasMany(Provider::class); }
    public function applications(): HasMany { return $this->hasMany(Application::class); }
}
