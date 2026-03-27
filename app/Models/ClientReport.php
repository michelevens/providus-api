<?php

namespace App\Models;

use App\Models\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientReport extends Model
{
    use BelongsToAgency;

    protected $fillable = [
        'agency_id', 'billing_client_id', 'report_type', 'period', 'total_claims',
        'claims_submitted', 'claims_paid', 'claims_denied', 'total_charged', 'total_collected',
        'total_denied_amount', 'total_adjustments', 'patient_responsibility', 'collection_rate',
        'clean_claim_rate', 'denial_rate', 'avg_days_to_pay', 'by_payer', 'denial_breakdown',
        'status', 'sent_date', 'notes', 'created_by',
    ];

    protected $casts = [
        'total_charged' => 'decimal:2', 'total_collected' => 'decimal:2',
        'total_denied_amount' => 'decimal:2', 'total_adjustments' => 'decimal:2',
        'patient_responsibility' => 'decimal:2', 'collection_rate' => 'decimal:1',
        'clean_claim_rate' => 'decimal:1', 'denial_rate' => 'decimal:1',
        'by_payer' => 'array', 'denial_breakdown' => 'array', 'sent_date' => 'date',
    ];

    public function billingClient(): BelongsTo { return $this->belongsTo(BillingClient::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
