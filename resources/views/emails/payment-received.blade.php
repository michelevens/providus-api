@extends('emails.layout')

@section('title', 'Payment Received')

@section('content')
<h2>Payment Received</h2>

<p>Hi {{ $invoice->client_name }},</p>

<p>Thank you! We've received your payment.</p>

<div class="success-box">
    <strong>Payment confirmed.</strong> Your invoice has been updated to reflect this payment.
</div>

<div class="details">
    <div class="detail-row">
        <span class="detail-label">Invoice #</span>
        <span class="detail-value">{{ $invoice->invoice_number ?: 'N/A' }}</span>
    </div>
    <div class="detail-row">
        <span class="detail-label">Amount Paid</span>
        <span class="detail-value" style="color:#059669;font-weight:700;font-size:16px;">${{ number_format($amountPaid, 2) }}</span>
    </div>
    <div class="detail-row">
        <span class="detail-label">Payment Date</span>
        <span class="detail-value">{{ now()->format('F j, Y') }}</span>
    </div>
    @if($remainingBalance > 0)
    <div class="detail-row">
        <span class="detail-label">Remaining Balance</span>
        <span class="detail-value" style="color:#d97706;">${{ number_format($remainingBalance, 2) }}</span>
    </div>
    @else
    <div class="detail-row">
        <span class="detail-label">Status</span>
        <span class="detail-value" style="color:#059669;">Paid in Full</span>
    </div>
    @endif
</div>

<p style="font-size:13px; color:#6b7280;">If you have any questions about this payment, please contact your credentialing specialist.</p>
@endsection
