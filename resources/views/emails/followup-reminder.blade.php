@extends('emails.layout')

@section('title', 'Follow-Up Reminder')

@section('content')
<h2>Follow-Up Reminder</h2>

<p>A follow-up is due for a credentialing application.</p>

<div class="alert-box">
    <strong>Action Needed.</strong> This follow-up was scheduled and is now due. Please take the necessary action.
</div>

<div class="details">
    <div class="detail-row">
        <span class="detail-label">Provider</span>
        <span class="detail-value">{{ $providerName }}</span>
    </div>
    <div class="detail-row">
        <span class="detail-label">Payer</span>
        <span class="detail-value">{{ $payerName }}</span>
    </div>
    <div class="detail-row">
        <span class="detail-label">Follow-Up Type</span>
        <span class="detail-value">{{ ucfirst(str_replace('_', ' ', $followup->type)) }}</span>
    </div>
    @if($followup->notes)
    <div class="detail-row">
        <span class="detail-label">Notes</span>
        <span class="detail-value">{{ \Illuminate\Support\Str::limit($followup->notes, 80) }}</span>
    </div>
    @endif
    <div class="detail-row">
        <span class="detail-label">Due Date</span>
        <span class="detail-value" style="color:#d97706;font-weight:700;">{{ $followup->due_date?->format('F j, Y') ?? 'Today' }}</span>
    </div>
</div>

<div style="text-align:center;">
    <a href="{{ config('app.frontend_url', 'https://app.credentik.com') }}/#followups" class="btn btn-primary">View Follow-Ups</a>
</div>
@endsection
