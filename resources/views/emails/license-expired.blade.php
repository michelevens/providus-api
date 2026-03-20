@extends('emails.layout')

@section('title', 'License Expired')

@section('content')
<h2>License Has Expired</h2>

<p>A license for <strong>{{ $providerName }}</strong> has expired.</p>

<div class="danger-box">
    <strong>Immediate Action Required.</strong> This license expired on {{ $license->expiration_date?->format('F j, Y') ?? 'N/A' }}. Practicing with an expired license may have regulatory consequences.
</div>

<div class="details">
    <div class="detail-row">
        <span class="detail-label">Provider</span>
        <span class="detail-value">{{ $providerName }}</span>
    </div>
    <div class="detail-row">
        <span class="detail-label">License Number</span>
        <span class="detail-value">{{ $license->license_number }}</span>
    </div>
    <div class="detail-row">
        <span class="detail-label">State</span>
        <span class="detail-value">{{ $license->state }}</span>
    </div>
    <div class="detail-row">
        <span class="detail-label">Type</span>
        <span class="detail-value">{{ $license->license_type }}</span>
    </div>
    <div class="detail-row">
        <span class="detail-label">Expired On</span>
        <span class="detail-value" style="color:#dc2626;font-weight:700;">{{ $license->expiration_date?->format('F j, Y') ?? 'N/A' }}</span>
    </div>
</div>

<div style="text-align:center;">
    <a href="{{ config('app.frontend_url', 'https://app.credentik.com') }}/#licenses" class="btn btn-danger">Renew Now</a>
</div>
@endsection
