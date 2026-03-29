<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientPaymentLedger extends Model
{
    protected $table = 'client_payment_ledger';

    protected $fillable = [
        'agency_id', 'billing_client_id', 'period',
        'total_collected', 'agency_fee', 'amount_remitted', 'outstanding',
        'remittance_date', 'remittance_method', 'remittance_reference',
        'status', 'notes', 'created_by',
    ];

    protected $casts = [
        'total_collected' => 'decimal:2',
        'agency_fee' => 'decimal:2',
        'amount_remitted' => 'decimal:2',
        'outstanding' => 'decimal:2',
        'remittance_date' => 'date',
    ];

    public function billingClient(): BelongsTo { return $this->belongsTo(BillingClient::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
