@php $agency = $agency ?? (object)['name' => 'Credentik', 'primary_color' => '#2C4A5A', 'accent_color' => '#D4A855', 'phone' => null, 'email' => null, 'address_city' => null, 'address_state' => null, 'address_zip' => null, 'logo_url' => null]; @endphp
@extends('emails.layout')

@section('title', 'Reset Your Password')

@section('content')
    <h1 style="margin:0 0 8px;font-size:22px;font-weight:700;color:#111827;">
        Reset Your Password
    </h1>
    <p style="margin:0 0 24px;font-size:14px;color:#6b7280;line-height:1.6;">
        Hi {{ $user->first_name }}, we received a request to reset your password.
        Click the button below to choose a new one.
    </p>

    {{-- CTA Button --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px;">
        <tr>
            <td align="center">
                <a href="{{ $resetUrl }}"
                   style="display:inline-block;background:{{ $agency->primary_color ?? '#2C4A5A' }};color:#ffffff;font-size:14px;font-weight:600;text-decoration:none;padding:12px 32px;border-radius:8px;">
                    Reset Password
                </a>
            </td>
        </tr>
    </table>

    <p style="margin:0 0 8px;font-size:13px;color:#9ca3af;">
        Or copy and paste this link:
    </p>
    <p style="margin:0 0 24px;font-size:12px;color:{{ $agency->primary_color ?? '#2C4A5A' }};word-break:break-all;">
        {{ $resetUrl }}
    </p>

    <div style="border-top:1px solid #e5e7eb;padding-top:16px;">
        <p style="margin:0;font-size:13px;color:#9ca3af;">
            This link expires in 2 hours. If you didn't request a password reset, no action is needed — your account is secure.
        </p>
    </div>
@endsection
