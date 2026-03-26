<?php

namespace App\Models;

use App\Models\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;

class NotificationLogEntry extends Model
{
    use BelongsToAgency;

    protected $table = 'notification_log';

    protected $fillable = [
        'agency_id', 'type', 'recipient_email', 'recipient_name',
        'subject', 'body', 'status', 'resend_id', 'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];
}
