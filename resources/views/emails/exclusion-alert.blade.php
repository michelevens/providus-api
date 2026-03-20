@extends('emails.layout')

@section('title', 'Exclusion Screening Alert')

@section('content')
<h2>Exclusion Screening Alert</h2>

<p>An exclusion screening has found a potential match for one of your providers.</p>

<div class="danger-box">
    <strong>Immediate Review Required.</strong> A potential match was found on a federal or state exclusion list. This must be investigated promptly to ensure compliance.
</div>

<div class="details">
    <div class="detail-row">
        <span class="detail-label">Provider</span>
        <span class="detail-value">{{ $providerName }}</span>
    </div>
    @if($npi)
    <div class="detail-row">
        <span class="detail-label">NPI</span>
        <span class="detail-value">{{ $npi }}</span>
    </div>
    @endif
    <div class="detail-row">
        <span class="detail-label">Source</span>
        <span class="detail-value">{{ $source ?? 'OIG/SAM' }}</span>
    </div>
    <div class="detail-row">
        <span class="detail-label">Screened On</span>
        <span class="detail-value">{{ now()->format('F j, Y') }}</span>
    </div>
</div>

<p>Please review this finding immediately and take appropriate action.</p>

<div style="text-align:center;">
    <a href="{{ config('app.frontend_url', 'https://app.credentik.com') }}/#exclusions" class="btn btn-danger">Review Exclusion</a>
</div>
@endsection
