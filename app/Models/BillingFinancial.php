<?php

namespace App\Models;

use App\Models\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingFinancial extends Model
{
    use BelongsToAgency;

    protected $fillable = [
        'agency_id', 'billing_client_id', 'period',
        'claims_submitted', 'amount_billed', 'amount_collected',
        'denial_count', 'denied_amount', 'adjustments',
        'patient_responsibility', 'created_by',
    ];

    protected $casts = [
        'amount_billed' => 'decimal:2',
        'amount_collected' => 'decimal:2',
        'denied_amount' => 'decimal:2',
        'adjustments' => 'decimal:2',
        'patient_responsibility' => 'decimal:2',
    ];

    public function billingClient(): BelongsTo { return $this->belongsTo(BillingClient::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
