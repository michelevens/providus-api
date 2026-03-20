@extends('emails.layout')

@section('title', "We'd love your feedback")

@section('content')
<h2>How Was Your Experience?</h2>

<p>Hi {{ $testimonial->patient_first_name ?? 'there' }},</p>

<p><strong>{{ $agency->name }}</strong> would love to hear about your experience. Your feedback helps others find quality care.</p>

<div style="text-align:center;margin:24px 0;">
    <span style="font-size:32px;letter-spacing:4px;">⭐⭐⭐⭐⭐</span>
</div>

<div style="text-align:center;">
    <a href="{{ $reviewUrl }}" class="btn btn-primary">Leave a Review</a>
</div>

<div class="info-box">
    It only takes a minute. Your review will display your chosen name — your email is never shared.
</div>

<p style="font-size:13px; color:#6b7280;">If you're having trouble clicking the button, copy and paste this URL:</p>
<p style="font-size:12px; word-break:break-all; color:#6b7280;">{{ $reviewUrl }}</p>
@endsection
