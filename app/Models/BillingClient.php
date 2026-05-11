<?php

namespace App\Models;

use App\Models\Traits\Auditable;
use App\Models\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BillingClient extends Model
{
    use Auditable, BelongsToAgency, SoftDeletes;

    protected $fillable = [
        'agency_id', 'organization_id', 'organization_name',
        'contact_name', 'contact_email', 'contact_phone',
        'billing_platform', 'monthly_fee', 'fee_structure',
        'payment_mode', 'agency_fee_percent',
        'status', 'start_date', 'notes', 'created_by',
        // Per-practice branding overrides. Optional — when set, surfaces
        // on patient-facing artifacts instead of the agency's brand.
        // BrandingResolver handles the precedence chain.
        'display_name', 'primary_color', 'accent_color', 'logo_url',
        'public_email', 'public_phone',
        'address_street', 'address_city', 'address_state', 'address_zip',
        'email_footer',
    ];

    protected $casts = [
        'monthly_fee' => 'decimal:2',
        'start_date' => 'date',
    ];

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function tasks(): HasMany { return $this->hasMany(BillingTask::class); }
    public function activities(): HasMany { return $this->hasMany(BillingActivity::class); }
    public function financials(): HasMany { return $this->hasMany(BillingFinancial::class); }
}
