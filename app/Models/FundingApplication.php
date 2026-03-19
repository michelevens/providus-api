<?php

namespace App\Models;

use App\Models\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FundingApplication extends Model
{
    use BelongsToAgency, SoftDeletes;

    protected $fillable = [
        'agency_id', 'funding_opportunity_id', 'title', 'stage',
        'amount_requested', 'amount_awarded', 'deadline',
        'submitted_at', 'notes', 'assigned_to',
    ];

    protected $casts = [
        'amount_requested' => 'decimal:2',
        'amount_awarded' => 'decimal:2',
        'deadline' => 'date',
        'submitted_at' => 'date',
    ];

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(FundingOpportunity::class, 'funding_opportunity_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
