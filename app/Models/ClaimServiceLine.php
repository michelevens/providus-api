<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClaimServiceLine extends Model
{
    protected $fillable = [
        'claim_id', 'line_number', 'cpt_code', 'cpt_description', 'modifiers',
        'icd_codes', 'units', 'charges', 'allowed_amount', 'paid_amount',
        'adjustment', 'patient_resp', 'status', 'denial_reason',
    ];

    protected $casts = [
        'units' => 'decimal:2', 'charges' => 'decimal:2', 'allowed_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2', 'adjustment' => 'decimal:2', 'patient_resp' => 'decimal:2',
    ];

    public function claim(): BelongsTo { return $this->belongsTo(Claim::class); }
}
