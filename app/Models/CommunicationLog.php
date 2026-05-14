<?php

namespace App\Models;

use App\Models\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommunicationLog extends Model
{
    use BelongsToAgency;

    protected $fillable = [
        'agency_id', 'application_id', 'provider_id', 'claim_id', 'billing_client_id',
        'entity_type', 'entity_id',
        'thread_id', 'parent_id',
        'direction', 'channel', 'subject', 'body', 'html_body',
        'contact_name', 'contact_info',
        'recipient_email', 'recipient_name',
        'outcome', 'delivery_status', 'resend_id',
        'duration_seconds', 'created_by',
        'delivered_at', 'bounced_at', 'read_at',
    ];

    protected $casts = [
        'delivered_at' => 'datetime',
        'bounced_at' => 'datetime',
        'read_at' => 'datetime',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function claim(): BelongsTo
    {
        return $this->belongsTo(Claim::class);
    }

    public function billingClient(): BelongsTo
    {
        return $this->belongsTo(BillingClient::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Other messages in the same thread (siblings + self). Self-join
     *  by thread_id so the thread view doesn't need an N+1. */
    public function threadMessages(): HasMany
    {
        return $this->hasMany(self::class, 'thread_id', 'thread_id');
    }
}
