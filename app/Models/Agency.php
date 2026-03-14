<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Agency extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'slug', 'npi', 'tax_id',
        'address_street', 'address_city', 'address_state', 'address_zip',
        'phone', 'email', 'website', 'taxonomy',
        'logo_url', 'primary_color', 'accent_color',
        'plan_tier', 'is_active', 'allowed_domains', 'embed_theme',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'allowed_domains' => 'array',
    ];

    public function config(): HasOne { return $this->hasOne(AgencyConfig::class); }
    public function users(): HasMany { return $this->hasMany(User::class); }
    public function organizations(): HasMany { return $this->hasMany(Organization::class); }
    public function providers(): HasMany { return $this->hasMany(Provider::class); }
    public function licenses(): HasMany { return $this->hasMany(License::class); }
    public function applications(): HasMany { return $this->hasMany(Application::class); }
    public function followups(): HasMany { return $this->hasMany(Followup::class); }
    public function activityLogs(): HasMany { return $this->hasMany(ActivityLog::class); }
    public function tasks(): HasMany { return $this->hasMany(Task::class); }
    public function strategyProfiles(): HasMany { return $this->hasMany(StrategyProfile::class); }
    public function payerPlans(): HasMany { return $this->hasMany(PayerPlan::class); }
    public function onboardTokens(): HasMany { return $this->hasMany(OnboardToken::class); }
    public function bookings(): HasMany { return $this->hasMany(Booking::class); }
    public function officeHours(): HasMany { return $this->hasMany(OfficeHour::class); }
    public function testimonials(): HasMany { return $this->hasMany(Testimonial::class); }
    public function eligibilityChecks(): HasMany { return $this->hasMany(EligibilityCheck::class); }
    public function caqhTracking(): HasMany { return $this->hasMany(CaqhTracking::class); }
}
