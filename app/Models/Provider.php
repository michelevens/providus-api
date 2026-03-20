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
        'first_name', 'last_name', 'date_of_birth', 'ssn_last4', 'gender',
        'credentials', 'npi', 'taxonomy', 'specialty',
        'email', 'phone',
        'address_street', 'address_city', 'address_state', 'address_zip',
        'caqh_id',
        // NP Collaborative Practice
        'supervising_physician', 'supervising_physician_npi',
        'collaborative_agreement_status', 'collaborative_agreement_expiry',
        // Scope of Practice
        'practice_authority', 'prescriptive_authority',
        'controlled_substance_authority', 'cs_schedule_authority',
        // Professional IDs
        'state_of_primary_license', 'medicaid_id', 'medicare_ptan',
        'languages_spoken', 'bio',
        // Status
        'is_active', 'onboarding_status', 'onboarding_completed_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'date_of_birth' => 'date',
        'collaborative_agreement_expiry' => 'date',
        'prescriptive_authority' => 'boolean',
        'controlled_substance_authority' => 'boolean',
        'onboarding_completed_at' => 'datetime',
    ];

    protected $hidden = ['ssn_last4'];

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
