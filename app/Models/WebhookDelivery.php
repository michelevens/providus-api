<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookDelivery extends Model
{
    public const STATUS_PENDING   = 'pending';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_FAILED    = 'failed';
    public const STATUS_ABANDONED = 'abandoned';

    /**
     * Truncate response_body to keep the audit trail bounded — receivers can return arbitrarily large payloads.
     */
    public const MAX_RESPONSE_BODY_BYTES = 4096;

    protected $fillable = [
        'webhook_id', 'agency_id', 'event', 'delivery_id', 'payload',
        'status', 'attempt_count', 'response_status', 'response_body',
        'error_message', 'first_attempt_at', 'completed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'attempt_count' => 'integer',
        'response_status' => 'integer',
        'first_attempt_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function webhook(): BelongsTo
    {
        return $this->belongsTo(Webhook::class);
    }
}
