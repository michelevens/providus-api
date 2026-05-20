@extends('emails.layout')

@section('title', 'Document Request')

@section('preheader')
{{ $agency->company_display_name ?: $agency->name }} needs you to upload {{ count($req->items ?? []) }} document{{ count($req->items ?? []) === 1 ? '' : 's' }}.
@endsection

@php
    $btnColor = $agency->primary_color ?? '#0891b2';
    $accentColor = $agency->accent_color ?? '#06b6d4';
    $agencyName = $agency->company_display_name ?: $agency->name;
    $items = $req->items ?? [];
@endphp

@section('content')

<table role="presentation" cellpadding="0" cellspacing="0" width="100%">
    <tr>
        <td style="padding-bottom:8px;">
            <p style="margin:0;font-size:13px;font-weight:600;color:{{ $btnColor }};letter-spacing:0.8px;text-transform:uppercase;">
                Document Request
            </p>
        </td>
    </tr>
    <tr>
        <td style="padding-bottom:16px;">
            <h1 style="margin:0;font-size:24px;font-weight:700;color:#0f172a;line-height:1.3;">
                @if(!empty($req->recipient_name))
                    Hi {{ explode(' ', trim($req->recipient_name))[0] }},
                @else
                    Hello,
                @endif
            </h1>
        </td>
    </tr>
    <tr>
        <td style="padding-bottom:24px;">
            <p style="margin:0;font-size:15px;color:#475569;line-height:1.65;">
                <strong style="color:#0f172a;">{{ $agencyName }}</strong>
                has requested the following document{{ count($items) === 1 ? '' : 's' }} from you.
                You can upload them securely using the link below — no account or password needed.
            </p>
        </td>
    </tr>
</table>

{{-- Checklist of requested items --}}
<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:24px;">
    <tr>
        <td style="background-color:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:18px 20px;">
            <p style="margin:0 0 12px;font-size:11px;font-weight:700;color:#94a3b8;letter-spacing:1.2px;text-transform:uppercase;">
                Requested ({{ count($items) }})
            </p>
            @foreach($items as $item)
                <div style="padding:8px 0;{{ !$loop->last ? 'border-bottom:1px solid #e2e8f0;' : '' }}">
                    <div style="font-size:14px;font-weight:600;color:#0f172a;">
                        {{ $item['label'] ?? $item['key'] }}
                        @if(($item['required'] ?? true))
                            <span style="font-size:10px;color:#dc2626;margin-left:6px;">REQUIRED</span>
                        @endif
                    </div>
                    @if(!empty($item['description']))
                        <div style="font-size:12px;color:#64748b;margin-top:4px;">{{ $item['description'] }}</div>
                    @endif
                </div>
            @endforeach
        </td>
    </tr>
</table>

@if(!empty($req->message))
{{-- Personal note from sender --}}
<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:24px;">
    <tr>
        <td style="background-color:#ecfeff;border-left:3px solid {{ $accentColor }};border-radius:6px;padding:14px 18px;">
            <p style="margin:0 0 6px;font-size:11px;font-weight:700;color:#0e7490;letter-spacing:0.6px;text-transform:uppercase;">
                Note from {{ $agencyName }}
            </p>
            <p style="margin:0;font-size:14px;color:#155e75;line-height:1.6;white-space:pre-line;">{{ $req->message }}</p>
        </td>
    </tr>
</table>
@endif

{{-- CTA button --}}
<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:24px;">
    <tr>
        <td align="center">
            <a href="{{ $publicUrl }}"
               style="display:inline-block;background-color:{{ $btnColor }};color:#ffffff;font-size:15px;font-weight:700;line-height:1.4;text-decoration:none;padding:16px 36px;border-radius:10px;box-shadow:0 2px 8px rgba(0,0,0,0.08);">
                Upload Documents
            </a>
        </td>
    </tr>
</table>

{{-- Secondary link (copy-paste fallback) --}}
<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:24px;">
    <tr>
        <td style="border-top:1px solid #e2e8f0;padding-top:18px;">
            <p style="margin:0 0 6px;font-size:12px;font-weight:600;color:#64748b;">Button not working? Copy and paste this link:</p>
            <p style="margin:0;font-size:12px;line-height:1.5;word-break:break-all;">
                <a href="{{ $publicUrl }}" style="color:{{ $btnColor }};text-decoration:underline;">{{ $publicUrl }}</a>
            </p>
        </td>
    </tr>
</table>

@if($req->expires_at)
<p style="margin:8px 0 0;font-size:12px;color:#64748b;line-height:1.6;">
    This link expires on <strong>{{ $req->expires_at->format('M j, Y') }}</strong>. After that you'll need to request a new link.
</p>
@endif

<p style="margin:24px 0 0;font-size:13px;color:#64748b;line-height:1.65;">
    Questions? Just reply to this email — {{ $agencyName }} will respond directly.
</p>

@endsection

@section('footer')
    <p style="margin:0;font-weight:600;color:#64748b;">&copy; {{ date('Y') }} {{ $agencyName }}</p>
    @if(!empty($agency->phone))<p style="margin:4px 0 0;">{{ $agency->phone }}</p>@endif
    <p style="margin:12px 0 0;font-size:11px;color:#cbd5e1;">
        Platform by <a href="https://credentik.com" style="color:{{ $btnColor }};text-decoration:none;font-weight:500;">Credentik</a>
    </p>
@endsection
