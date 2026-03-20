@extends('emails.layout')

@section('title', 'Subscription Activated')

@section('content')
<h2>Your Subscription is Active!</h2>

<p>Hi {{ $user->first_name }},</p>

<p>Thank you for subscribing to <strong>Credentik {{ ucfirst($planName) }}</strong>. Your subscription is now active and you have full access to all features in your plan.</p>

<div class="success-box">
    <strong>You're all set!</strong> Your subscription is active and billing will occur {{ $billingCycle ?? 'monthly' }}.
</div>

<div class="details">
    <div class="detail-row">
        <span class="detail-label">Plan</span>
        <span class="detail-value">{{ ucfirst($planName) }}</span>
    </div>
    <div class="detail-row">
        <span class="detail-label">Billing Cycle</span>
        <span class="detail-value">{{ ucfirst($billingCycle ?? 'Monthly') }}</span>
    </div>
    <div class="detail-row">
        <span class="detail-label">Started On</span>
        <span class="detail-value">{{ now()->format('F j, Y') }}</span>
    </div>
</div>

<div style="text-align:center;">
    <a href="{{ config('app.frontend_url', 'https://app.credentik.com') }}" class="btn btn-primary">Go to Dashboard</a>
</div>

<p style="font-size:13px; color:#6b7280;">Manage your subscription anytime from your account settings. Need help? Reply to this email.</p>
@endsection
