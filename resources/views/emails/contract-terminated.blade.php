@extends('emails.layout')

@section('title', 'Contract Terminated')

@section('content')
<h2>Contract Terminated</h2>

<p>The following contract has been terminated.</p>

<div class="danger-box">
    <strong>This contract is no longer active.</strong> All obligations under this agreement have ended as of the termination date.
</div>

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
    <div class="detail-row">
        <span class="detail-label">Terminated On</span>
        <span class="detail-value" style="color:#dc2626;font-weight:700;">{{ now()->format('F j, Y') }}</span>
    </div>
    @if($reason)
    <div class="detail-row">
        <span class="detail-label">Reason</span>
        <span class="detail-value">{{ $reason }}</span>
    </div>
    @endif
</div>

<div style="text-align:center;">
    <a href="{{ config('app.frontend_url', 'https://app.credentik.com') }}/#contracts" class="btn btn-outline">View Contracts</a>
</div>
@endsection
