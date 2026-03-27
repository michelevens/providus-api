<?php

namespace App\Models;

use App\Models\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClaimPayment extends Model
{
    use BelongsToAgency, SoftDeletes;

    protected $fillable = [
        'agency_id', 'billing_client_id', 'payer_name', 'payment_type',
        'check_number', 'trace_number', 'payment_date', 'deposit_date',
        'total_amount', 'posted_amount', 'remaining_amount', 'status',
        'notes', 'posted_by', 'posted_at', 'created_by',
    ];

    protected $casts = [
        'payment_date' => 'date', 'deposit_date' => 'date', 'posted_at' => 'datetime',
        'total_amount' => 'decimal:2', 'posted_amount' => 'decimal:2', 'remaining_amount' => 'decimal:2',
    ];

    public function recalculate(): void
    {
        $this->posted_amount = $this->allocations()->sum('paid_amount');
        $this->remaining_amount = $this->total_amount - $this->posted_amount;
        if ($this->remaining_amount <= 0) $this->status = 'posted';
        elseif ($this->posted_amount > 0) $this->status = 'partial';
        $this->save();
    }

    public function billingClient(): BelongsTo { return $this->belongsTo(BillingClient::class); }
    public function allocations(): HasMany { return $this->hasMany(PaymentAllocation::class, 'claim_payment_id'); }
    public function poster(): BelongsTo { return $this->belongsTo(User::class, 'posted_by'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
