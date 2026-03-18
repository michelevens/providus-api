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
        'stripe_customer_id', 'stripe_subscription_id', 'stripe_price_id',
        'subscription_status', 'trial_ends_at', 'subscription_ends_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'allowed_domains' => 'array',
        'trial_ends_at' => 'datetime',
        'subscription_ends_at' => 'datetime',
    ];

    public function isOnTrial(): bool
    {
        return $this->subscription_status === 'trialing' && $this->trial_ends_at?->isFuture();
    }

    public function isSubscribed(): bool
    {
        return in_array($this->subscription_status, ['active', 'trialing']);
    }

    public function hasExpired(): bool
    {
        if ($this->subscription_status === 'trialing') return $this->trial_ends_at?->isPast() ?? false;
        return $this->subscription_status === 'canceled' && $this->subscription_ends_at?->isPast();
    }

    public const PLAN_LIMITS = [
        'starter' => ['providers' => 5, 'users' => 3, 'applications' => 50],
        'professional' => ['providers' => 25, 'users' => 10, 'applications' => 500],
        'enterprise' => ['providers' => -1, 'users' => -1, 'applications' => -1],
    ];

    public function planLimit(string $resource): int
    {
        return self::PLAN_LIMITS[$this->plan_tier][$resource] ?? 0;
    }

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
