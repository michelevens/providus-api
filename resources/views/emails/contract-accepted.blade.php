@extends('emails.layout')

@section('title', 'Contract Accepted')

@section('content')
<h2>Contract Accepted</h2>

<p>Great news! The contract has been signed and accepted.</p>

<div class="success-box">
    <strong>Contract executed successfully.</strong> Both parties are now bound by the terms of this agreement.
</div>

<div class="details">
    <div class="detail-row">
        <span class="detail-label">Contract</span>
        <span class="detail-value">{{ $contract->title ?? 'Service Agreement' }}</span>
    </div>
    <div class="detail-row">
        <span class="detail-label">Signed By</span>
        <span class="detail-value">{{ $signedBy }}</span>
    </div>
    <div class="detail-row">
        <span class="detail-label">Date Signed</span>
        <span class="detail-value">{{ now()->format('F j, Y') }}</span>
    </div>
    @if($contract->start_date)
    <div class="detail-row">
        <span class="detail-label">Effective Date</span>
        <span class="detail-value">{{ $contract->start_date->format('F j, Y') }}</span>
    </div>
    @endif
</div>

<div style="text-align:center;">
    <a href="{{ config('app.frontend_url', 'https://app.credentik.com') }}/#contracts" class="btn btn-primary">View Contracts</a>
</div>
@endsection
