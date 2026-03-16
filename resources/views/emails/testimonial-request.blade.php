@extends('emails.layout')

@section('title', "We'd love your feedback")

@section('content')
    <h1 style="margin:0 0 8px;font-size:22px;font-weight:700;color:#111827;">
        How Was Your Experience?
    </h1>
    <p style="margin:0 0 24px;font-size:14px;color:#6b7280;line-height:1.6;">
        Hi {{ $testimonial->patient_first_name ?? 'there' }},
        <strong style="color:#111827;">{{ $agency->name }}</strong> would love to hear about your experience.
        Your feedback helps others find quality care.
    </p>

    {{-- Star illustration --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px;">
        <tr>
            <td align="center" style="font-size:28px;letter-spacing:4px;color:{{ $agency->accent_color ?? '#D4A855' }};">
                &#9733;&#9733;&#9733;&#9733;&#9733;
            </td>
        </tr>
    </table>

    {{-- CTA Button --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px;">
        <tr>
            <td align="center">
                <a href="{{ $reviewUrl }}"
                   style="display:inline-block;background:{{ $agency->primary_color ?? '#2C4A5A' }};color:#ffffff;font-size:14px;font-weight:600;text-decoration:none;padding:12px 32px;border-radius:8px;">
                    Leave a Review
                </a>
            </td>
        </tr>
    </table>

    <p style="margin:0 0 8px;font-size:13px;color:#9ca3af;">
        Or copy and paste this link:
    </p>
    <p style="margin:0 0 24px;font-size:12px;color:{{ $agency->primary_color ?? '#2C4A5A' }};word-break:break-all;">
        {{ $reviewUrl }}
    </p>

    <div style="border-top:1px solid #e5e7eb;padding-top:16px;">
        <p style="margin:0;font-size:13px;color:#9ca3af;">
            It only takes a minute. Your review will be displayed with your chosen display name — your email is never shared.
        </p>
    </div>
@endsection
