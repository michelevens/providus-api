<?php

namespace App\Models;

use App\Models\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingActivity extends Model
{
    use BelongsToAgency;

    protected $fillable = [
        'agency_id', 'billing_client_id', 'activity_type',
        'provider_name', 'payer_name', 'activity_date',
        'amount', 'quantity', 'reference', 'notes', 'created_by',
    ];

    protected $casts = [
        'activity_date' => 'date',
        'amount' => 'decimal:2',
        'quantity' => 'integer',
    ];

    public function billingClient(): BelongsTo { return $this->belongsTo(BillingClient::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
