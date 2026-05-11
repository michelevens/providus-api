@extends('emails.layout')

@section('title', 'Payment Reminder')

@section('preheader')
A payment of ${{ $amount }} is awaiting action on your {{ $descLabel }}.
@endsection

@php
    // Resolve once for reuse in the CTA + accent dividers. Layout already
    // exposes $primaryColor; we just shadow it locally for the buttons.
    $btnColor = $agency->primary_color ?? '#0891b2';
    $accentColor = $agency->accent_color ?? '#06b6d4';
@endphp

@section('content')

{{-- Greeting --}}
<table role="presentation" cellpadding="0" cellspacing="0" width="100%">
    <tr>
        <td style="padding-bottom:8px;">
            <p style="margin:0;font-size:14px;font-weight:600;color:{{ $btnColor }};letter-spacing:0.8px;text-transform:uppercase;" class="text-dark">
                Payment Reminder
            </p>
        </td>
    </tr>
    <tr>
        <td style="padding-bottom:16px;">
            <h1 style="margin:0;font-size:24px;font-weight:700;color:#0f172a;line-height:1.3;letter-spacing:-0.4px;" class="text-dark">
                @if(!empty($link->patient_name))
                    Hi {{ explode(' ', trim($link->patient_name))[0] }},
                @else
                    Hello,
                @endif
            </h1>
        </td>
    </tr>
    <tr>
        <td style="padding-bottom:24px;">
            <p style="margin:0;font-size:15px;color:#475569;line-height:1.65;" class="text-body">
                This is a friendly reminder that a payment is awaiting your action on your {{ $descLabel }} with
                <strong style="color:#0f172a;" class="text-dark">{{ $agency->company_display_name ?: $agency->name }}</strong>.
            </p>
        </td>
    </tr>
</table>

{{-- Amount card — the visual focal point --}}
<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:28px;">
    <tr>
        <td style="background-color:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:28px 24px;text-align:center;" class="details-bg details-border">
            <p style="margin:0 0 6px;font-size:11px;font-weight:700;color:#94a3b8;letter-spacing:1.5px;text-transform:uppercase;" class="text-muted">
                Amount Due
            </p>
            <p style="margin:0;font-size:40px;font-weight:800;color:#0f172a;line-height:1.1;letter-spacing:-1px;" class="text-dark">
                ${{ $amount }}
            </p>
            @if($link->expires_at)
            <p style="margin:14px 0 0;font-size:12px;color:#64748b;" class="text-muted">
                Link expires {{ $link->expires_at->format('M j, Y') }}
            </p>
            @endif
        </td>
    </tr>
</table>

{{-- CTA button — bulletproof for Outlook + mobile-full-width --}}
<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:24px;">
    <tr>
        <td align="center">
            <!--[if mso]>
            <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="{{ $publicUrl }}" style="height:52px;v-text-anchor:middle;width:280px;" arcsize="20%" stroke="f" fillcolor="{{ $btnColor }}">
                <w:anchorlock/>
                <center style="color:#ffffff;font-family:Arial,sans-serif;font-size:15px;font-weight:700;">Pay ${{ $amount }} Now</center>
            </v:roundrect>
            <![endif]-->
            <!--[if !mso]><!-- -->
            <a href="{{ $publicUrl }}"
               style="display:inline-block;background-color:{{ $btnColor }};color:#ffffff;font-size:15px;font-weight:700;line-height:1.4;text-decoration:none;padding:16px 36px;border-radius:10px;letter-spacing:0.2px;mso-padding-alt:0;box-shadow:0 2px 8px rgba(0,0,0,0.08);"
               class="btn-full">
                Pay ${{ $amount }} Now
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

{{-- Security/trust callout --}}
<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:8px;">
    <tr>
        <td style="background-color:#ecfeff;border-left:3px solid {{ $accentColor }};border-radius:6px;padding:14px 16px;" class="callout-info">
            <p style="margin:0;font-size:12px;color:#155e75;line-height:1.6;">
                <strong style="color:#0e7490;">&#128274; Secure payment.</strong>
                Your payment is processed by <strong>Stripe</strong>. We never see or store your card details.
            </p>
        </td>
    </tr>
</table>

{{-- Closing note --}}
<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="margin-top:24px;">
    <tr>
        <td>
            <p style="margin:0;font-size:13px;color:#64748b;line-height:1.65;" class="text-muted">
                If you've already submitted payment, please disregard this notice. Questions? Reply to this email and someone from
                <strong style="color:#0f172a;" class="text-dark">{{ $agency->company_display_name ?: $agency->name }}</strong>
                will get back to you.
            </p>
        </td>
    </tr>
</table>

@endsection

@section('footer')
    <p style="margin:0;font-weight:600;color:#64748b;" class="text-muted">
        &copy; {{ date('Y') }} {{ $agency->company_display_name ?: $agency->name }}
    </p>
    @if(!empty($agency->phone))
        <p style="margin:4px 0 0;">{{ $agency->phone }}</p>
    @endif
    @if(!empty($agency->address_city))
        <p style="margin:4px 0 0;">{{ $agency->address_city }}{{ $agency->address_state ? ', ' . $agency->address_state : '' }} {{ $agency->address_zip }}</p>
    @endif
    @if(!empty($agency->email_footer))
        <p style="margin:8px 0 0;font-size:11px;line-height:1.5;color:#94a3b8;">{!! nl2br(e($agency->email_footer)) !!}</p>
    @endif
    <p style="margin:12px 0 0;font-size:11px;color:#cbd5e1;">
        Payments powered by <a href="https://stripe.com" style="color:{{ $btnColor }};text-decoration:none;font-weight:500;">Stripe</a> &middot; Platform by <a href="https://credentik.com" style="color:{{ $btnColor }};text-decoration:none;font-weight:500;">Credentik</a>
    </p>
@endsection
