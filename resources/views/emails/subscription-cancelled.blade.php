@extends('emails.layout')

@section('title', 'Subscription Cancelled')

@section('content')
<h2>Subscription Cancelled</h2>

<p>Hi {{ $user->first_name }},</p>

<p>Your Credentik subscription has been cancelled as requested.</p>

<div class="alert-box">
    <strong>Your access continues until {{ $endsAt }}.</strong> After that date, your account will be downgraded. Your data will be preserved.
</div>

<div class="details">
    <div class="detail-row">
        <span class="detail-label">Plan</span>
        <span class="detail-value">{{ ucfirst($planName) }}</span>
    </div>
    <div class="detail-row">
        <span class="detail-label">Access Until</span>
        <span class="detail-value">{{ $endsAt }}</span>
    </div>
</div>

<p>Changed your mind? You can reactivate anytime before your access period ends.</p>

<div style="text-align:center;">
    <a href="{{ config('app.frontend_url', 'https://app.credentik.com') }}/#settings" class="btn btn-primary">Reactivate Subscription</a>
</div>

<p style="font-size:13px; color:#6b7280;">We'd love to know why you cancelled. Reply to this email with any feedback — it helps us improve.</p>
@endsection
