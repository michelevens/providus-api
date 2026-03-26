<?php

namespace App\Models;

use App\Models\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;

class NotificationPreference extends Model
{
    use BelongsToAgency;

    protected $fillable = [
        'agency_id', 'default_recipient_email', 'status_changes',
        'license_expiration', 'license_expiration_days',
        'document_requests', 'weekly_summary',
    ];

    protected $casts = [
        'status_changes' => 'boolean',
        'license_expiration' => 'boolean',
        'license_expiration_days' => 'integer',
        'document_requests' => 'boolean',
        'weekly_summary' => 'boolean',
    ];
}
