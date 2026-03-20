@extends('emails.layout')

@section('title', 'Document Uploaded')

@section('content')
<h2>New Document Uploaded</h2>

<p>A new document has been uploaded for <strong>{{ $providerName }}</strong>.</p>

<div class="details">
    <div class="detail-row">
        <span class="detail-label">Provider</span>
        <span class="detail-value">{{ $providerName }}</span>
    </div>
    <div class="detail-row">
        <span class="detail-label">Document</span>
        <span class="detail-value">{{ $documentName }}</span>
    </div>
    @if($documentType)
    <div class="detail-row">
        <span class="detail-label">Type</span>
        <span class="detail-value">{{ $documentType }}</span>
    </div>
    @endif
    <div class="detail-row">
        <span class="detail-label">Uploaded On</span>
        <span class="detail-value">{{ now()->format('F j, Y g:i A') }}</span>
    </div>
</div>

<div class="info-box">
    This document is now available in the provider's profile for review.
</div>

<div style="text-align:center;">
    <a href="{{ config('app.frontend_url', 'https://app.credentik.com') }}/#providers" class="btn btn-primary">View Provider</a>
</div>
@endsection
