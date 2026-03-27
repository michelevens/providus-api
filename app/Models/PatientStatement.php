<?php

namespace App\Models;

use App\Models\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PatientStatement extends Model
{
    use BelongsToAgency, SoftDeletes;

    protected $fillable = [
        'agency_id', 'billing_client_id', 'claim_id', 'patient_name', 'patient_email',
        'patient_phone', 'patient_address', 'total_charges', 'insurance_paid', 'adjustments',
        'patient_balance', 'amount_paid', 'status', 'statement_date', 'due_date',
        'last_sent_date', 'times_sent', 'notes', 'created_by',
    ];

    protected $casts = [
        'total_charges' => 'decimal:2', 'insurance_paid' => 'decimal:2', 'adjustments' => 'decimal:2',
        'patient_balance' => 'decimal:2', 'amount_paid' => 'decimal:2',
        'statement_date' => 'date', 'due_date' => 'date', 'last_sent_date' => 'date',
    ];

    public function claim(): BelongsTo { return $this->belongsTo(Claim::class); }
    public function billingClient(): BelongsTo { return $this->belongsTo(BillingClient::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
