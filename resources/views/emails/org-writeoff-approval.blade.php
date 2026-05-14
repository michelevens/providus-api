@extends('emails.layout')

@section('title', 'Write-off approval')

@section('content')
<h2>Approval requested: write-off on a claim</h2>

<p>
    {{ $requestedBy?->first_name ? trim(($requestedBy->first_name ?? '') . ' ' . ($requestedBy->last_name ?? '')) : 'Your billing team' }}
    is asking you to approve a write-off on one of your claims. This means the unpaid balance below would be
    permanently removed from the receivable, and the claim closed.
</p>

<div style="text-align:center;margin:24px 0;">
    <div style="display:inline-block;background:#fef3c7;border:2px solid #d97706;border-radius:12px;padding:20px 32px;">
        <div style="font-size:11px;font-weight:700;color:#92400e;text-transform:uppercase;letter-spacing:1px;">Write-off amount</div>
        <div style="font-size:32px;font-weight:700;color:#92400e;margin-top:4px;">${{ number_format((float) $request->amount, 2) }}</div>
        @if($request->category)
            <div style="font-size:12px;color:#92400e;margin-top:4px;text-transform:capitalize;">{{ str_replace('_', ' ', $request->category) }}</div>
        @endif
    </div>
</div>

<div class="details">
    <div class="detail-row">
        <span class="detail-label">Patient</span>
        <span class="detail-value">{{ $claim->patient_name ?: '—' }}</span>
    </div>
    <div class="detail-row">
        <span class="detail-label">Claim number</span>
        <span class="detail-value" style="font-family:monospace;">{{ $claim->claim_number ?: ('#' . $claim->id) }}</span>
    </div>
    @if($claim->payer_name)
    <div class="detail-row">
        <span class="detail-label">Payer</span>
        <span class="detail-value">{{ $claim->payer_name }}</span>
    </div>
    @endif
    @if($claim->date_of_service)
    <div class="detail-row">
        <span class="detail-label">Date of service</span>
        <span class="detail-value">{{ \Carbon\Carbon::parse($claim->date_of_service)->format('M j, Y') }}</span>
    </div>
    @endif
    <div class="detail-row">
        <span class="detail-label">Total charges</span>
        <span class="detail-value">${{ number_format((float) $claim->total_charges, 2) }}</span>
    </div>
    <div class="detail-row">
        <span class="detail-label">Already paid</span>
        <span class="detail-value">${{ number_format((float) $claim->total_paid, 2) }}</span>
    </div>
    <div class="detail-row">
        <span class="detail-label">Outstanding balance</span>
        <span class="detail-value" style="color:#dc2626;font-weight:600;">${{ number_format((float) $claim->balance, 2) }}</span>
    </div>
</div>

@if($request->reason)
<div style="background:#f9fafb;border-left:4px solid #6b7280;padding:12px 16px;margin:20px 0;border-radius:4px;">
    <div style="font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">Reason given</div>
    <div style="font-size:14px;color:#374151;line-height:1.5;white-space:pre-wrap;">{{ $request->reason }}</div>
</div>
@endif

<div style="text-align:center;margin:32px 0;">
    <a href="{{ $approveUrl }}" class="btn btn-primary" style="background:#059669;border-color:#047857;display:inline-block;margin-bottom:12px;">
        ✓ Approve write-off
    </a>
    <div style="font-size:13px;color:#6b7280;margin-top:8px;">
        Not approving? <a href="{{ $rejectUrl }}" style="color:#dc2626;">Reject this request</a>
        or <a href="{{ $portalUrl }}" style="color:#0891b2;">view full claim details</a> first.
    </div>
</div>

<div class="alert-box" style="margin-top:24px;font-size:12px;">
    <strong>What happens when you approve?</strong>
    The ${{ number_format((float) $request->amount, 2) }} balance is removed from your claim's receivable.
    The claim status is recorded as <code>written_off</code>. Your billing team can see the audit trail of who approved and when.
    @if($request->expires_at)
    <br><br>
    <strong>If you don't respond by {{ \Carbon\Carbon::parse($request->expires_at)->format('M j, Y') }}</strong>,
    the request automatically escalates to {{ $agency->name }}'s internal approval queue.
    @endif
</div>

<p style="font-size:11px;color:#9ca3af;margin-top:24px;text-align:center;">
    This email is the official approval request. The buttons above use a one-time secure link tied to this specific claim;
    they cannot be used to approve any other claim or write-off.
</p>
@endsection
