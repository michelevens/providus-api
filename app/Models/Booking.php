<?php

namespace App\Models;

use App\Models\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    use BelongsToAgency;

    protected $fillable = [
        'agency_id', 'confirmation_code', 'appointment_date', 'appointment_time',
        'duration_minutes', 'service_type', 'patient_first_name', 'patient_last_name',
        'patient_email', 'patient_phone', 'patient_dob', 'insurance', 'reason',
        'status', 'calendar_event_id', 'notes', 'reminder_sent',
    ];

    protected $casts = [
        'appointment_date' => 'date',
        'patient_dob' => 'date',
        'reminder_sent' => 'boolean',
    ];

    public static function generateConfirmationCode(string $dateStr): string
    {
        $dateCode = str_replace('-', '', $dateStr);
        $random = strtoupper(substr(bin2hex(random_bytes(2)), 0, 3));
        return "ENH-{$dateCode}-{$random}";
    }
}
