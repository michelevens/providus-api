@extends('emails.layout')

@section('title', 'Provider Onboarded')

@section('content')
<h2>Welcome Aboard!</h2>

<p>Hi {{ $provider->first_name }},</p>

<p>Your onboarding with <strong>{{ $agency->name }}</strong> is complete. Your profile is now active and ready for the credentialing process.</p>

<div class="success-box">
    <strong>Onboarding Complete.</strong> Your information has been received and verified.
</div>

<div class="details">
    <div class="detail-row">
        <span class="detail-label">Provider</span>
        <span class="detail-value">{{ $provider->first_name }} {{ $provider->last_name }}</span>
    </div>
    @if($provider->npi)
    <div class="detail-row">
        <span class="detail-label">NPI</span>
        <span class="detail-value">{{ $provider->npi }}</span>
    </div>
    @endif
    @if($provider->specialty)
    <div class="detail-row">
        <span class="detail-label">Specialty</span>
        <span class="detail-value">{{ $provider->specialty }}</span>
    </div>
    @endif
</div>

<p>Here's what happens next:</p>
<ul style="color:#374151;font-size:14px;line-height:2;padding-left:20px;">
    <li>Your credentials will be verified</li>
    <li>License expirations will be monitored</li>
    <li>Credentialing applications will be submitted on your behalf</li>
</ul>

<div style="text-align:center;">
    <a href="{{ config('app.frontend_url', 'https://app.credentik.com') }}" class="btn btn-primary">Go to Dashboard</a>
</div>
@endsection
