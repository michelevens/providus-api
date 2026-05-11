@extends('emails.layout')

@section('title', 'Statement of Account')

@section('preheader')
Statement of account: {{ number_format((float) $statement->patient_balance, 2) }} balance due.
@endsection

@section('content')
<h2 style="margin:0 0 12px;font-size:20px;font-weight:700;color:#0f172a;letter-spacing:-0.2px;" class="text-dark">
    Statement of Account
</h2>

<p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#334155;" class="text-body">
    Hi {{ explode(' ', $statement->patient_name ?: 'there')[0] }},
</p>

<p style="margin:0 0 20px;font-size:15px;line-height:1.6;color:#334155;" class="text-body">
    Here is a summary of your account with {{ $agency->company_display_name ?: $agency->name }}.
    Please review the balance below and contact us if you have any questions.
</p>

<div class="details" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:18px 20px;margin:0 0 20px;">
    <table role="presentation" cellpadding="0" cellspacing="0" width="100%" class="detail-table">
        <tr>
            <td class="detail-border" style="padding:8px 0;border-bottom:1px solid #e2e8f0;font-size:13px;color:#64748b;" class="text-muted">Patient</td>
            <td class="detail-border" align="right" style="padding:8px 0;border-bottom:1px solid #e2e8f0;font-size:14px;font-weight:600;color:#0f172a;" class="text-dark">{{ $statement->patient_name ?: '—' }}</td>
        </tr>
        @if($statement->statement_date)
        <tr>
            <td class="detail-border" style="padding:8px 0;border-bottom:1px solid #e2e8f0;font-size:13px;color:#64748b;" class="text-muted">Statement date</td>
            <td class="detail-border" align="right" style="padding:8px 0;border-bottom:1px solid #e2e8f0;font-size:14px;color:#334155;" class="text-body">{{ $statement->statement_date->format('F j, Y') }}</td>
        </tr>
        @endif
        <tr>
            <td class="detail-border" style="padding:8px 0;border-bottom:1px solid #e2e8f0;font-size:13px;color:#64748b;" class="text-muted">Total charges</td>
            <td class="detail-border" align="right" style="padding:8px 0;border-bottom:1px solid #e2e8f0;font-size:14px;color:#334155;" class="text-body">${{ number_format((float) $statement->total_charges, 2) }}</td>
        </tr>
        <tr>
            <td class="detail-border" style="padding:8px 0;border-bottom:1px solid #e2e8f0;font-size:13px;color:#64748b;" class="text-muted">Insurance paid</td>
            <td class="detail-border" align="right" style="padding:8px 0;border-bottom:1px solid #e2e8f0;font-size:14px;color:#16a34a;">−${{ number_format((float) $statement->insurance_paid, 2) }}</td>
        </tr>
        @if((float) $statement->adjustments > 0)
        <tr>
            <td class="detail-border" style="padding:8px 0;border-bottom:1px solid #e2e8f0;font-size:13px;color:#64748b;" class="text-muted">Adjustments</td>
            <td class="detail-border" align="right" style="padding:8px 0;border-bottom:1px solid #e2e8f0;font-size:14px;color:#334155;" class="text-body">−${{ number_format((float) $statement->adjustments, 2) }}</td>
        </tr>
        @endif
        @if((float) $statement->amount_paid > 0)
        <tr>
            <td class="detail-border" style="padding:8px 0;border-bottom:1px solid #e2e8f0;font-size:13px;color:#64748b;" class="text-muted">Already paid</td>
            <td class="detail-border" align="right" style="padding:8px 0;border-bottom:1px solid #e2e8f0;font-size:14px;color:#16a34a;">−${{ number_format((float) $statement->amount_paid, 2) }}</td>
        </tr>
        @endif
        <tr>
            <td style="padding:12px 0 4px;font-size:14px;font-weight:700;color:#0f172a;" class="text-dark">Balance due</td>
            <td align="right" style="padding:12px 0 4px;font-size:20px;font-weight:700;color:{{ $primaryColor }};">
                ${{ number_format((float) $statement->patient_balance - (float) $statement->amount_paid, 2) }}
            </td>
        </tr>
        @if($statement->due_date)
        <tr>
            <td style="padding:0 0 4px;font-size:12px;color:#64748b;" class="text-muted">Due by</td>
            <td align="right" style="padding:0 0 4px;font-size:13px;font-weight:600;color:#d97706;">{{ $statement->due_date->format('F j, Y') }}</td>
        </tr>
        @endif
    </table>
</div>

@if($payUrl)
<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="margin:0 0 20px;">
    <tr>
        <td align="center">
            <a href="{{ $payUrl }}"
               style="display:inline-block;background:{{ $primaryColor }};color:#ffffff;padding:14px 28px;border-radius:8px;font-size:15px;font-weight:600;text-decoration:none;letter-spacing:0.2px;"
               class="btn-full">
                Pay Balance Online
            </a>
        </td>
    </tr>
</table>
<p style="margin:0 0 16px;font-size:12px;line-height:1.5;color:#64748b;text-align:center;" class="text-muted">
    Or copy this link: <span style="color:{{ $primaryColor }};word-break:break-all;">{{ $payUrl }}</span>
</p>
@endif

@if($statement->notes)
<div style="background:#fffbeb;border:1px solid #fde68a;border-radius:6px;padding:12px 16px;margin:0 0 20px;" class="callout-warning">
    <p style="margin:0;font-size:13px;line-height:1.5;color:#78350f;">
        <strong style="color:#92400e;">Note from {{ $agency->name }}:</strong> {{ $statement->notes }}
    </p>
</div>
@endif

<p style="margin:0;font-size:13px;color:#64748b;line-height:1.6;" class="text-muted">
    If you've already submitted payment, please disregard this notice. Questions about this statement? Reply to this
    email or contact us directly.
</p>
@endsection
