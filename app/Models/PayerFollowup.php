<?php

namespace App\Models;

use App\Models\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PayerFollowup extends Model
{
    use BelongsToAgency, SoftDeletes;

    protected $fillable = [
        'agency_id', 'claim_id', 'contact_method', 'payer_name', 'payer_rep',
        'reference_number', 'outcome', 'notes', 'followup_date', 'followup_completed', 'created_by',
    ];

    protected $casts = [
        'followup_date' => 'date', 'followup_completed' => 'boolean',
    ];

    public function claim(): BelongsTo { return $this->belongsTo(Claim::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
