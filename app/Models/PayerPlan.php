<?php

namespace App\Models;

use App\Models\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayerPlan extends Model
{
    use BelongsToAgency;

    protected $fillable = [
        'agency_id', 'payer_id', 'name', 'type', 'state',
        'reimbursement_rate', 'notes',
    ];

    protected $casts = ['reimbursement_rate' => 'decimal:2'];

    public function payer(): BelongsTo { return $this->belongsTo(Payer::class); }
}
