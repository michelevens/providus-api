@extends('emails.layout')

@section('title', 'Contract Ready for Review')

@section('content')
<h2>Contract Ready for Review</h2>

<p>Hi {{ $recipientName }},</p>

<p>A new contract from <strong>{{ $agency->name }}</strong> is ready for your review and signature.</p>

<div class="details">
    <div class="detail-row">
        <span class="detail-label">Contract</span>
        <span class="detail-value">{{ $contract->title ?? 'Service Agreement' }}</span>
    </div>
    @if($contract->provider)
    <div class="detail-row">
        <span class="detail-label">Provider</span>
        <span class="detail-value">{{ $contract->provider->full_name ?? '' }}</span>
    </div>
    @endif
    @if($contract->start_date)
    <div class="detail-row">
        <span class="detail-label">Start Date</span>
        <span class="detail-value">{{ $contract->start_date->format('F j, Y') }}</span>
    </div>
    @endif
</div>

<p>Please review the contract terms and accept or request changes.</p>

<div style="text-align:center;">
    <a href="{{ $contractUrl }}" class="btn btn-primary">Review & Sign Contract</a>
</div>

<div class="info-box">
    This is a secure link. Only you can access this contract using the link above.
</div>

<p style="font-size:13px; color:#6b7280;">If you're having trouble clicking the button, copy and paste this URL:</p>
<p style="font-size:12px; word-break:break-all; color:#6b7280;">{{ $contractUrl }}</p>
@endsection
