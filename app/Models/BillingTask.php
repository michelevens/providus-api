<?php

namespace App\Models;

use App\Models\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BillingTask extends Model
{
    use BelongsToAgency, SoftDeletes;

    protected $fillable = [
        'agency_id', 'billing_client_id', 'claim_id', 'title', 'description',
        'provider_name', 'category', 'priority', 'status',
        'due_date', 'assigned_to', 'created_by', 'completed_at',
        'source', 'source_key', 'dismissed',
    ];

    protected $casts = [
        'due_date' => 'date',
        'completed_at' => 'datetime',
    ];

    public function billingClient(): BelongsTo { return $this->belongsTo(BillingClient::class); }
    public function assignee(): BelongsTo { return $this->belongsTo(User::class, 'assigned_to'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
