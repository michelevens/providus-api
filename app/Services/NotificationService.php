<?php

namespace App\Services;

use App\Models\Notification;

class NotificationService
{
    public static function send(int $agencyId, string $type, string $title, array $options = []): Notification
    {
        return Notification::create([
            'agency_id' => $agencyId,
            'user_id' => $options['user_id'] ?? null,
            'type' => $type,
            'title' => $title,
            'body' => $options['body'] ?? null,
            'icon' => $options['icon'] ?? self::iconForType($type),
            'link' => $options['link'] ?? null,
            'linkable_type' => $options['linkable_type'] ?? null,
            'linkable_id' => $options['linkable_id'] ?? null,
        ]);
    }

    private static function iconForType(string $type): string
    {
        return match ($type) {
            'license_expiring' => 'alert',
            'task_due' => 'task',
            'app_status' => 'app',
            'followup_overdue' => 'clock',
            'booking_new' => 'calendar',
            'user_invited' => 'user',
            'review_submitted' => 'star',
            'eligibility_checked' => 'shield',
            default => 'bell',
        };
    }
}
