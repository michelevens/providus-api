@extends('emails.layout')

@section('title', "You're invited to {{ $agency->name }}")

@section('content')
    <h1 style="margin:0 0 8px;font-size:22px;font-weight:700;color:#111827;">
        You're Invited
    </h1>
    <p style="margin:0 0 24px;font-size:14px;color:#6b7280;line-height:1.6;">
        Hi {{ $user->first_name }}, you've been invited to join
        <strong style="color:#111827;">{{ $agency->name }}</strong> on Credentik
        as {{ ucfirst($user->role) }}.
    </p>

    {{-- CTA Button --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px;">
        <tr>
            <td align="center">
                <a href="{{ $inviteUrl }}"
                   style="display:inline-block;background:{{ $agency->primary_color ?? '#2C4A5A' }};color:#ffffff;font-size:14px;font-weight:600;text-decoration:none;padding:12px 32px;border-radius:8px;">
                    Accept Invitation
                </a>
            </td>
        </tr>
    </table>

    <p style="margin:0 0 8px;font-size:13px;color:#9ca3af;">
        Or copy and paste this link into your browser:
    </p>
    <p style="margin:0 0 24px;font-size:12px;color:{{ $agency->primary_color ?? '#2C4A5A' }};word-break:break-all;">
        {{ $inviteUrl }}
    </p>

    <div style="border-top:1px solid #e5e7eb;padding-top:16px;">
        <p style="margin:0;font-size:13px;color:#9ca3af;">
            This invitation expires in 7 days. If you didn't expect this email, you can safely ignore it.
        </p>
    </div>
@endsection
