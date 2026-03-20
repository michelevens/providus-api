@php
    $urgency = $daysUntilExpiry <= 7 ? 'danger' : ($daysUntilExpiry <= 30 ? 'alert' : 'info');
    $urgencyColor = match($urgency) {
        'danger' => '#dc2626',
        'alert' => '#d97706',
        default => '#0891b2',
    };
@endphp
@extends('emails.layout')

@section('title', 'License Expiring Soon')

@section('content')
<h2>License Expiring Soon</h2>

<p>A license for <strong>{{ $providerName }}</strong> is expiring in
    <strong style="color:{{ $urgencyColor }};">{{ $daysUntilExpiry }} day{{ $daysUntilExpiry !== 1 ? 's' : '' }}</strong>.
</p>

<div class="{{ $urgency }}-box">
    <strong>{{ $urgency === 'danger' ? 'Urgent — Expiring Very Soon!' : ($urgency === 'alert' ? 'Expiring Soon — Please Renew' : 'Renewal Reminder') }}</strong>
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
        <span class="detail-label">Expiration Date</span>
        <span class="detail-value" style="color:{{ $urgencyColor }};font-weight:700;">{{ $license->expiration_date?->format('F j, Y') ?? 'N/A' }}</span>
    </div>
    <div class="detail-row">
        <span class="detail-label">Days Remaining</span>
        <span class="detail-value" style="color:{{ $urgencyColor }};font-weight:700;">{{ $daysUntilExpiry }}</span>
    </div>
</div>

<p>Please take action to renew this license before it expires. Failure to renew may impact credentialing status.</p>

<div style="text-align:center;">
    <a href="{{ config('app.frontend_url', 'https://app.credentik.com') }}/#licenses" class="btn btn-primary">Manage Licenses</a>
</div>
@endsection
