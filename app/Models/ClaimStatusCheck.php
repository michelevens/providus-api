<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClaimStatusCheck extends Model
{
    protected $fillable = [
        'claim_id', 'checked_at', 'source',
        'status_code', 'status_category', 'status_text',
        'paid_amount', 'paid_date', 'check_number',
        'raw_response', 'user_id',
    ];

    protected $casts = [
        'checked_at' => 'datetime',
        'paid_date' => 'date',
        'paid_amount' => 'decimal:2',
        'raw_response' => 'array',
    ];

    public function claim(): BelongsTo { return $this->belongsTo(Claim::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
