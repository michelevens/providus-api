@extends('emails.layout')

@section('title', 'Appointment Confirmed')

@section('content')
<h2>Appointment Confirmed</h2>

<p>Hi {{ $booking->patient_first_name }}, your appointment has been booked.</p>

<div class="details">
    <div class="detail-row">
        <span class="detail-label">Date & Time</span>
        <span class="detail-value">{{ \Carbon\Carbon::parse($booking->appointment_date)->format('l, F j, Y') }} at {{ $booking->appointment_time }}</span>
    </div>
    @if($booking->service_type)
    <div class="detail-row">
        <span class="detail-label">Service</span>
        <span class="detail-value">{{ $booking->service_type }}</span>
    </div>
    @endif
    <div class="detail-row">
        <span class="detail-label">Duration</span>
        <span class="detail-value">{{ $booking->duration_minutes }} minutes</span>
    </div>
    <div class="detail-row">
        <span class="detail-label">Confirmation Code</span>
        <span class="detail-value" style="color:{{ $agency->primary_color ?? '#0891b2' }};font-weight:700;font-size:16px;letter-spacing:1px;">{{ $booking->confirmation_code }}</span>
    </div>
</div>

<div class="info-box">
    <strong>Please arrive 10 minutes early.</strong> If you need to cancel or reschedule, contact us as soon as possible.
</div>

@if(!empty($agency->phone) || !empty($agency->email))
<p style="font-size:13px; color:#6b7280;">
    Need to make changes?
    @if(!empty($agency->phone)) Call <strong>{{ $agency->phone }}</strong>@endif
    @if(!empty($agency->phone) && !empty($agency->email)) or @endif
    @if(!empty($agency->email)) email <a href="mailto:{{ $agency->email }}" style="color:{{ $agency->primary_color ?? '#0891b2' }};">{{ $agency->email }}</a>@endif
</p>
@endif
@endsection
