<?php

namespace App\Models;

use App\Models\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EligibilityCheck extends Model
{
    use BelongsToAgency;

    protected $fillable = [
        'agency_id', 'billing_client_id', 'patient_name', 'patient_dob', 'member_id',
        'payer_name', 'payer_id', 'provider_npi', 'status', 'is_active',
        'coverage_start', 'coverage_end', 'plan_name', 'plan_type', 'group_number',
        'copay', 'deductible', 'deductible_met', 'out_of_pocket_max', 'oop_met',
        'raw_response', 'error_message', 'created_by',
    ];

    protected $casts = [
        'patient_dob' => 'date', 'is_active' => 'boolean',
        'coverage_start' => 'date', 'coverage_end' => 'date',
        'copay' => 'decimal:2', 'deductible' => 'decimal:2', 'deductible_met' => 'decimal:2',
        'out_of_pocket_max' => 'decimal:2', 'oop_met' => 'decimal:2',
        'raw_response' => 'array',
    ];

    public function billingClient(): BelongsTo { return $this->belongsTo(BillingClient::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
