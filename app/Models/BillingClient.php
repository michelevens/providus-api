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
        // Pricing fields used by the auto-invoice generator (2026-05-18).
        // These supersede the older flat monthly_fee + fee_structure pair;
        // the legacy columns are kept on the row for backward compat.
        'rcm_pricing_model', 'rcm_percentage_rate', 'rcm_percentage_basis',
        'rcm_per_claim_rate', 'rcm_monthly_base',
        'credentialing_pricing_model', 'credentialing_per_app_rate', 'credentialing_per_provider_rate',
        'setup_fee', 'setup_fee_billed',
        'statement_send_rate', 'eligibility_check_rate', 'denial_appeal_rate',
        'contract_start_date', 'contract_end_date', 'billing_day',
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
        'rcm_percentage_rate' => 'decimal:4',
        'rcm_per_claim_rate' => 'decimal:2',
        'rcm_monthly_base' => 'decimal:2',
        'credentialing_per_app_rate' => 'decimal:2',
        'credentialing_per_provider_rate' => 'decimal:2',
        'setup_fee' => 'decimal:2',
        'setup_fee_billed' => 'boolean',
        'statement_send_rate' => 'decimal:2',
        'eligibility_check_rate' => 'decimal:2',
        'denial_appeal_rate' => 'decimal:2',
        'contract_start_date' => 'date',
        'contract_end_date' => 'date',
    ];

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function tasks(): HasMany { return $this->hasMany(BillingTask::class); }
    public function activities(): HasMany { return $this->hasMany(BillingActivity::class); }
    public function financials(): HasMany { return $this->hasMany(BillingFinancial::class); }
}
