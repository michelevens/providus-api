<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentAllocation extends Model
{
    protected $fillable = [
        'claim_payment_id', 'claim_id', 'service_line_number',
        'charged_amount', 'allowed_amount', 'paid_amount', 'adjustment_amount',
        'patient_responsibility', 'copay', 'coinsurance', 'deductible',
        'adjustment_codes', 'remark_codes',
    ];

    protected $casts = [
        'charged_amount' => 'decimal:2', 'allowed_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2', 'adjustment_amount' => 'decimal:2',
        'patient_responsibility' => 'decimal:2', 'copay' => 'decimal:2',
        'coinsurance' => 'decimal:2', 'deductible' => 'decimal:2',
    ];

    public function payment(): BelongsTo { return $this->belongsTo(ClaimPayment::class, 'claim_payment_id'); }
    public function claim(): BelongsTo { return $this->belongsTo(Claim::class); }
}
