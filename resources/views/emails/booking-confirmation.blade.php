@extends('emails.layout')

@section('title', 'Appointment Confirmed')

@section('content')
    <h1 style="margin:0 0 8px;font-size:22px;font-weight:700;color:#111827;">
        Appointment Confirmed
    </h1>
    <p style="margin:0 0 24px;font-size:14px;color:#6b7280;">
        Hi {{ $booking->patient_first_name }}, your appointment has been booked.
    </p>

    {{-- Appointment Details Card --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f9fafb;border-radius:8px;border:1px solid #e5e7eb;margin-bottom:24px;">
        <tr>
            <td style="padding:20px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="padding-bottom:12px;">
                            <div style="font-size:11px;text-transform:uppercase;letter-spacing:0.5px;color:#9ca3af;font-weight:600;">Date & Time</div>
                            <div style="font-size:16px;font-weight:600;color:#111827;margin-top:4px;">
                                {{ \Carbon\Carbon::parse($booking->appointment_date)->format('l, F j, Y') }}
                                at {{ $booking->appointment_time }}
                            </div>
                        </td>
                    </tr>
                    @if($booking->service_type)
                    <tr>
                        <td style="padding-bottom:12px;">
                            <div style="font-size:11px;text-transform:uppercase;letter-spacing:0.5px;color:#9ca3af;font-weight:600;">Service</div>
                            <div style="font-size:14px;color:#374151;margin-top:4px;">{{ $booking->service_type }}</div>
                        </td>
                    </tr>
                    @endif
                    <tr>
                        <td style="padding-bottom:12px;">
                            <div style="font-size:11px;text-transform:uppercase;letter-spacing:0.5px;color:#9ca3af;font-weight:600;">Duration</div>
                            <div style="font-size:14px;color:#374151;margin-top:4px;">{{ $booking->duration_minutes }} minutes</div>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <div style="font-size:11px;text-transform:uppercase;letter-spacing:0.5px;color:#9ca3af;font-weight:600;">Confirmation Code</div>
                            <div style="font-size:18px;font-weight:700;color:{{ $agency->primary_color ?? '#2C4A5A' }};margin-top:4px;letter-spacing:1px;">
                                {{ $booking->confirmation_code }}
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <p style="margin:0 0 16px;font-size:14px;color:#6b7280;line-height:1.6;">
        Please arrive 10 minutes before your scheduled time. If you need to cancel or reschedule,
        contact us using the information below.
    </p>

    @if($agency->phone || $agency->email)
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-top:1px solid #e5e7eb;padding-top:16px;">
        <tr>
            <td style="font-size:13px;color:#6b7280;">
                @if($agency->phone)
                    <strong>Phone:</strong> {{ $agency->phone }}<br>
                @endif
                @if($agency->email)
                    <strong>Email:</strong> <a href="mailto:{{ $agency->email }}" style="color:{{ $agency->primary_color ?? '#2C4A5A' }};">{{ $agency->email }}</a>
                @endif
            </td>
        </tr>
    </table>
    @endif
@endsection
