@extends('emails.layout')

@section('title', 'Credential Verification Complete')

@section('content')
<h2>Verification Complete</h2>

<p>A credential for <strong>{{ $providerName }}</strong> has been verified.</p>

<div class="success-box">
    <strong>✓ Verified.</strong> This credential has been confirmed as valid and active.
</div>

<div class="details">
    <div class="detail-row">
        <span class="detail-label">Provider</span>
        <span class="detail-value">{{ $providerName }}</span>
    </div>
    <div class="detail-row">
        <span class="detail-label">Credential</span>
        <span class="detail-value">{{ $credentialName }}</span>
    </div>
    <div class="detail-row">
        <span class="detail-label">Verification Source</span>
        <span class="detail-value">{{ $verificationSource ?? 'Primary Source' }}</span>
    </div>
    <div class="detail-row">
        <span class="detail-label">Verified On</span>
        <span class="detail-value">{{ now()->format('F j, Y') }}</span>
    </div>
    @if($expirationDate)
    <div class="detail-row">
        <span class="detail-label">Valid Until</span>
        <span class="detail-value">{{ $expirationDate }}</span>
    </div>
    @endif
</div>

<div style="text-align:center;">
    <a href="{{ config('app.frontend_url', 'https://app.credentik.com') }}/#licenses" class="btn btn-outline">View Licenses</a>
</div>
@endsection
