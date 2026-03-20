@php
    $urgency = $daysUntilExpiry <= 7 ? 'danger' : ($daysUntilExpiry <= 30 ? 'alert' : 'info');
    $urgencyColor = match($urgency) {
        'danger' => '#dc2626',
        'alert' => '#d97706',
        default => '#0891b2',
    };
@endphp
@extends('emails.layout')

@section('title', 'Document Expiring Soon')

@section('content')
<h2>Document Expiring Soon</h2>

<p>A document for <strong>{{ $providerName }}</strong> is expiring in
    <strong style="color:{{ $urgencyColor }};">{{ $daysUntilExpiry }} day{{ $daysUntilExpiry !== 1 ? 's' : '' }}</strong>.
</p>

<div class="{{ $urgency }}-box">
    <strong>{{ $urgency === 'danger' ? 'Urgent — Expiring Very Soon!' : ($urgency === 'alert' ? 'Expiring Soon' : 'Renewal Reminder') }}</strong>
    — Please upload an updated version before it expires.
</div>

<div class="details">
    <div class="detail-row">
        <span class="detail-label">Provider</span>
        <span class="detail-value">{{ $providerName }}</span>
    </div>
    <div class="detail-row">
        <span class="detail-label">Document</span>
        <span class="detail-value">{{ $documentName }}</span>
    </div>
    <div class="detail-row">
        <span class="detail-label">Expiration Date</span>
        <span class="detail-value" style="color:{{ $urgencyColor }};font-weight:700;">{{ $expirationDate }}</span>
    </div>
    <div class="detail-row">
        <span class="detail-label">Days Remaining</span>
        <span class="detail-value" style="color:{{ $urgencyColor }};font-weight:700;">{{ $daysUntilExpiry }}</span>
    </div>
</div>

<div style="text-align:center;">
    <a href="{{ config('app.frontend_url', 'https://app.credentik.com') }}/#providers" class="btn btn-primary">Upload Updated Document</a>
</div>
@endsection
