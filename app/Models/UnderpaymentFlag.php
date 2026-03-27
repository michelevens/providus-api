<?php

namespace App\Models;

use App\Models\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnderpaymentFlag extends Model
{
    use BelongsToAgency;

    protected $fillable = [
        'agency_id', 'claim_id', 'cpt_code', 'expected_amount', 'paid_amount',
        'variance', 'status', 'notes', 'reviewed_by', 'reviewed_at', 'created_by',
    ];

    protected $casts = [
        'expected_amount' => 'decimal:2', 'paid_amount' => 'decimal:2', 'variance' => 'decimal:2',
        'reviewed_at' => 'datetime',
    ];

    public function claim(): BelongsTo { return $this->belongsTo(Claim::class); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }
}
