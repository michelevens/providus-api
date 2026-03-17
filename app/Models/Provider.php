<?php

namespace App\Models;

use App\Models\Traits\Auditable;
use App\Models\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Provider extends Model
{
    use BelongsToAgency, SoftDeletes, Auditable;

    protected $fillable = [
        'agency_id', 'organization_id', 'user_id', 'legacy_id',
        'first_name', 'last_name', 'credentials', 'npi', 'taxonomy',
        'specialty', 'email', 'phone', 'caqh_id', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function licenses(): HasMany { return $this->hasMany(License::class); }
    public function applications(): HasMany { return $this->hasMany(Application::class); }
    public function caqhTracking(): HasOne { return $this->hasOne(CaqhTracking::class); }
    public function deaRegistrations(): HasMany { return $this->hasMany(DeaRegistration::class); }
    public function licenseVerifications(): HasMany { return $this->hasMany(LicenseVerification::class); }

    public function getFullNameAttribute(): string
    {
        $name = trim($this->first_name . ' ' . $this->last_name);
        return $this->credentials ? "$name, $this->credentials" : $name;
    }
}
