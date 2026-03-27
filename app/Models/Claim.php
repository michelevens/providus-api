<?php

namespace App\Models;

use App\Models\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Claim extends Model
{
    use BelongsToAgency, SoftDeletes;

    protected $fillable = [
        'agency_id', 'billing_client_id', 'claim_number', 'claim_type', 'status',
        'provider_id', 'provider_name', 'patient_name', 'patient_dob', 'patient_member_id',
        'payer_name', 'payer_id_number', 'date_of_service', 'date_of_service_end',
        'place_of_service', 'facility_name', 'referring_provider', 'authorization_number',
        'total_charges', 'total_allowed', 'total_paid', 'patient_responsibility',
        'adjustments', 'balance', 'submission_method', 'clearinghouse',
        'submitted_date', 'acknowledged_date', 'adjudicated_date', 'paid_date',
        'check_number', 'denial_reason', 'denial_codes', 'appeal_deadline',
        'notes', 'created_by',
    ];

    protected $casts = [
        'date_of_service' => 'date',
        'date_of_service_end' => 'date',
        'submitted_date' => 'date',
        'acknowledged_date' => 'date',
        'adjudicated_date' => 'date',
        'paid_date' => 'date',
        'appeal_deadline' => 'date',
        'total_charges' => 'decimal:2',
        'total_allowed' => 'decimal:2',
        'total_paid' => 'decimal:2',
        'patient_responsibility' => 'decimal:2',
        'adjustments' => 'decimal:2',
        'balance' => 'decimal:2',
    ];

    public function recalculate(): void
    {
        $this->total_charges = $this->serviceLines()->sum('charges');
        $this->total_paid = $this->serviceLines()->sum('paid_amount');
        $this->adjustments = $this->serviceLines()->sum('adjustment');
        $this->patient_responsibility = $this->serviceLines()->sum('patient_resp');
        $this->balance = $this->total_charges - $this->total_paid - $this->adjustments;
        if ($this->balance <= 0 && $this->total_charges > 0) $this->status = 'paid';
        elseif ($this->total_paid > 0) $this->status = 'partial_paid';
        $this->save();
    }

    public function billingClient(): BelongsTo { return $this->belongsTo(BillingClient::class); }
    public function provider(): BelongsTo { return $this->belongsTo(Provider::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function serviceLines(): HasMany { return $this->hasMany(ClaimServiceLine::class); }
    public function denials(): HasMany { return $this->hasMany(ClaimDenial::class); }
    public function paymentAllocations(): HasMany { return $this->hasMany(PaymentAllocation::class); }
}
