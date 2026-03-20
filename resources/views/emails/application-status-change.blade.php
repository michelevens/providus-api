@php
    $statusConfig = [
        'approved'    => ['color' => '#059669', 'bg' => '#ecfdf5', 'icon' => '✓', 'label' => 'Approved'],
        'denied'      => ['color' => '#dc2626', 'bg' => '#fef2f2', 'icon' => '✕', 'label' => 'Denied'],
        'submitted'   => ['color' => '#2563eb', 'bg' => '#eff6ff', 'icon' => '→', 'label' => 'Submitted'],
        'in_progress' => ['color' => '#d97706', 'bg' => '#fffbeb', 'icon' => '⟳', 'label' => 'In Progress'],
        'on_hold'     => ['color' => '#7c3aed', 'bg' => '#f5f3ff', 'icon' => '⏸', 'label' => 'On Hold'],
        'not_started' => ['color' => '#6b7280', 'bg' => '#f9fafb', 'icon' => '○', 'label' => 'Not Started'],
    ];
    $cfg = $statusConfig[$newStatus] ?? ['color' => '#6b7280', 'bg' => '#f9fafb', 'icon' => '•', 'label' => ucfirst(str_replace('_', ' ', $newStatus))];
@endphp
@extends('emails.layout')

@section('title', 'Application Status Update')

@section('content')
<h2>Application Status Update</h2>

<p>The credentialing application for <strong>{{ $providerName }}</strong> with <strong>{{ $payerName }}</strong> has been updated.</p>

<div style="text-align:center;margin:24px 0;">
    <div style="display:inline-block;background:{{ $cfg['bg'] }};border:2px solid {{ $cfg['color'] }};border-radius:12px;padding:20px 32px;">
        <div style="font-size:28px;margin-bottom:4px;">{{ $cfg['icon'] }}</div>
        <div style="font-size:18px;font-weight:700;color:{{ $cfg['color'] }};">{{ $cfg['label'] }}</div>
    </div>
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
        <span class="detail-label">Previous Status</span>
        <span class="detail-value">{{ ucfirst(str_replace('_', ' ', $oldStatus)) }}</span>
    </div>
    <div class="detail-row">
        <span class="detail-label">New Status</span>
        <span class="detail-value" style="color:{{ $cfg['color'] }};">{{ $cfg['label'] }}</span>
    </div>
</div>

@if($newStatus === 'approved')
<div class="success-box">
    <strong>Congratulations!</strong> This application has been approved. The provider is now credentialed with this payer.
</div>
@elseif($newStatus === 'denied')
<div class="danger-box">
    <strong>Action Required.</strong> This application has been denied. Please review the denial reason and determine next steps.
</div>
@elseif($newStatus === 'on_hold')
<div class="alert-box">
    <strong>Application On Hold.</strong> Additional information or documentation may be needed to proceed.
</div>
@endif

<div style="text-align:center;">
    <a href="{{ config('app.frontend_url', 'https://app.credentik.com') }}/#applications" class="btn btn-primary">View Application</a>
</div>
@endsection
