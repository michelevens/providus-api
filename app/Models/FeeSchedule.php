<?php

namespace App\Models;

use App\Models\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FeeSchedule extends Model
{
    use BelongsToAgency, SoftDeletes;

    protected $fillable = [
        'agency_id', 'billing_client_id', 'payer_name', 'cpt_code', 'cpt_description',
        'modifier', 'contracted_rate', 'expected_allowed', 'effective_date', 'termination_date',
        'plan_type', 'notes', 'created_by',
    ];

    protected $casts = [
        'contracted_rate' => 'decimal:2', 'expected_allowed' => 'decimal:2',
        'effective_date' => 'date', 'termination_date' => 'date',
    ];

    public function billingClient(): BelongsTo { return $this->belongsTo(BillingClient::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
