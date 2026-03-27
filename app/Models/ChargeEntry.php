<?php

namespace App\Models;

use App\Models\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChargeEntry extends Model
{
    use BelongsToAgency, SoftDeletes;

    protected $fillable = [
        'agency_id', 'billing_client_id', 'provider_id', 'provider_name',
        'patient_name', 'payer_name', 'date_of_service', 'cpt_code', 'cpt_description',
        'modifiers', 'icd_codes', 'icd_descriptions', 'units', 'charge_amount',
        'allowed_amount', 'place_of_service', 'facility_name', 'authorization_number',
        'status', 'claim_id', 'notes', 'created_by',
    ];

    protected $casts = [
        'date_of_service' => 'date', 'units' => 'decimal:2',
        'charge_amount' => 'decimal:2', 'allowed_amount' => 'decimal:2',
    ];

    public function billingClient(): BelongsTo { return $this->belongsTo(BillingClient::class); }
    public function provider(): BelongsTo { return $this->belongsTo(Provider::class); }
    public function claim(): BelongsTo { return $this->belongsTo(Claim::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
