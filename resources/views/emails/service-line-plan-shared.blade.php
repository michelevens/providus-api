@extends('emails.layout')

@section('title', $link->service_line_name . ' — Business Plan')

@section('preheader')
{{ $agency->company_display_name ?: $agency->name }} has prepared a {{ $link->service_line_name }} business plan for you.
@endsection

@php
    $btnColor = $agency->primary_color ?? '#0891b2';
    $accentColor = $agency->accent_color ?? '#06b6d4';
    $agencyName = $agency->company_display_name ?: $agency->name;
@endphp

@section('content')

{{-- Eyebrow --}}
<table role="presentation" cellpadding="0" cellspacing="0" width="100%">
    <tr>
        <td style="padding-bottom:8px;">
            <p style="margin:0;font-size:13px;font-weight:600;color:{{ $btnColor }};letter-spacing:0.8px;text-transform:uppercase;" class="text-dark">
                Business Plan
            </p>
        </td>
    </tr>
    <tr>
        <td style="padding-bottom:16px;">
            <h1 style="margin:0;font-size:24px;font-weight:700;color:#0f172a;line-height:1.3;letter-spacing:-0.4px;" class="text-dark">
                @if(!empty($link->recipient_name))
                    Hi {{ explode(' ', trim($link->recipient_name))[0] }},
                @else
                    Hello,
                @endif
            </h1>
        </td>
    </tr>
    <tr>
        <td style="padding-bottom:24px;">
            <p style="margin:0;font-size:15px;color:#475569;line-height:1.65;" class="text-body">
                <strong style="color:#0f172a;" class="text-dark">{{ $agencyName }}</strong>
                has prepared a strategic business plan for the
                <strong style="color:#0f172a;" class="text-dark">{{ $link->service_line_name }}</strong>
                service line. You can review and download it below.
            </p>
        </td>
    </tr>
</table>

{{-- Plan card — the visual focal point --}}
<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:28px;">
    <tr>
        <td style="background-color:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:24px;text-align:center;" class="details-bg details-border">
            <p style="margin:0 0 6px;font-size:11px;font-weight:700;color:#94a3b8;letter-spacing:1.5px;text-transform:uppercase;" class="text-muted">
                Service Line
            </p>
            <p style="margin:0;font-size:22px;font-weight:800;color:#0f172a;line-height:1.2;letter-spacing:-0.3px;" class="text-dark">
                {{ $link->service_line_name }}
            </p>
            <p style="margin:14px 0 0;font-size:12px;color:#64748b;" class="text-muted">
                Prepared by {{ $agencyName }}
                @if($link->expires_at)
                    &middot; Link expires {{ $link->expires_at->format('M j, Y') }}
                @endif
            </p>
        </td>
    </tr>
</table>

@if(!empty($link->message))
{{-- Personal note from the sender --}}
<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:24px;">
    <tr>
        <td style="background-color:#ecfeff;border-left:3px solid {{ $accentColor }};border-radius:6px;padding:16px 18px;" class="callout-info">
            <p style="margin:0 0 6px;font-size:11px;font-weight:700;color:#0e7490;letter-spacing:0.6px;text-transform:uppercase;">
                Note from {{ $agencyName }}
            </p>
            <p style="margin:0;font-size:14px;color:#155e75;line-height:1.6;white-space:pre-line;">{{ $link->message }}</p>
        </td>
    </tr>
</table>
@endif

{{-- CTA button --}}
<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:24px;">
    <tr>
        <td align="center">
            <!--[if mso]>
            <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="{{ $publicUrl }}" style="height:52px;v-text-anchor:middle;width:300px;" arcsize="20%" stroke="f" fillcolor="{{ $btnColor }}">
                <w:anchorlock/>
                <center style="color:#ffffff;font-family:Arial,sans-serif;font-size:15px;font-weight:700;">Download Business Plan</center>
            </v:roundrect>
            <![endif]-->
            <!--[if !mso]><!-- -->
            <a href="{{ $publicUrl }}"
               style="display:inline-block;background-color:{{ $btnColor }};color:#ffffff;font-size:15px;font-weight:700;line-height:1.4;text-decoration:none;padding:16px 36px;border-radius:10px;letter-spacing:0.2px;mso-padding-alt:0;box-shadow:0 2px 8px rgba(0,0,0,0.08);"
               class="btn-full">
                Download Business Plan
            </a>
            <!--<![endif]-->
        </td>
    </tr>
</table>

{{-- Secondary link (copy-paste fallback) --}}
<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:28px;">
    <tr>
        <td style="border-top:1px solid #e2e8f0;padding-top:20px;" class="detail-border">
            <p style="margin:0 0 6px;font-size:12px;font-weight:600;color:#64748b;letter-spacing:0.3px;" class="text-muted">
                Button not working? Copy and paste this link:
            </p>
            <p style="margin:0;font-size:12px;line-height:1.5;word-break:break-all;">
                <a href="{{ $publicUrl }}" style="color:{{ $btnColor }};text-decoration:underline;">{{ $publicUrl }}</a>
            </p>
        </td>
    </tr>
</table>

{{-- Closing note --}}
<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="margin-top:24px;">
    <tr>
        <td>
            <p style="margin:0;font-size:13px;color:#64748b;line-height:1.65;" class="text-muted">
                Questions about this plan? Just reply to this email — someone from
                <strong style="color:#0f172a;" class="text-dark">{{ $agencyName }}</strong>
                will get back to you.
            </p>
        </td>
    </tr>
</table>

@endsection

@section('footer')
    <p style="margin:0;font-weight:600;color:#64748b;" class="text-muted">
        &copy; {{ date('Y') }} {{ $agencyName }}
    </p>
    @if(!empty($agency->phone))
        <p style="margin:4px 0 0;">{{ $agency->phone }}</p>
    @endif
    @if(!empty($agency->address_city))
        <p style="margin:4px 0 0;">{{ $agency->address_city }}{{ $agency->address_state ? ', ' . $agency->address_state : '' }} {{ $agency->address_zip }}</p>
    @endif
    <p style="margin:12px 0 0;font-size:11px;color:#cbd5e1;">
        Platform by <a href="https://credentik.com" style="color:{{ $btnColor }};text-decoration:none;font-weight:500;">Credentik</a>
    </p>
@endsection
