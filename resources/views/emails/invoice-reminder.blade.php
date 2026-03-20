@extends('emails.layout')

@section('title', 'Payment Reminder')

@section('content')
<h2>Invoice Payment Reminder</h2>

<p>Hi {{ $invoice->client_name }},</p>

<p>This is a friendly reminder that the following invoice is due.</p>

<div class="details">
    <div class="detail-row">
        <span class="detail-label">Invoice #</span>
        <span class="detail-value">{{ $invoice->invoice_number ?: 'N/A' }}</span>
    </div>
    <div class="detail-row">
        <span class="detail-label">Amount Due</span>
        <span class="detail-value" style="font-size:16px;color:#111827;">${{ number_format($invoice->total, 2) }}</span>
    </div>
    <div class="detail-row">
        <span class="detail-label">Due Date</span>
        <span class="detail-value" style="color:#d97706;font-weight:700;">{{ $invoice->due_date?->format('F j, Y') ?? 'N/A' }}</span>
    </div>
    @if($invoice->description)
    <div class="detail-row">
        <span class="detail-label">Description</span>
        <span class="detail-value">{{ $invoice->description }}</span>
    </div>
    @endif
</div>

<div class="alert-box">
    Please arrange payment at your earliest convenience to avoid any service interruptions.
</div>

<p style="font-size:13px; color:#6b7280;">If you've already submitted payment, please disregard this notice. Questions? Reply to this email or contact your credentialing specialist.</p>
@endsection
