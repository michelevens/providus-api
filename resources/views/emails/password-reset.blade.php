@extends('emails.layout')

@section('title', 'Reset Your Password')

@section('content')
<h2>Reset Your Password</h2>

<p>Hi {{ $user->first_name }},</p>

<p>We received a request to reset the password for your account. Click the button below to set a new password.</p>

<a href="{{ $resetUrl }}" class="btn btn-primary">Reset Password</a>

<div class="alert-box">
    <strong>This link expires in 2 hours.</strong> If you did not request a password reset, please ignore this email — your account is secure.
</div>

<p style="font-size:13px; color:#6b7280;">If you're having trouble clicking the button, copy and paste this URL into your browser:</p>
<p style="font-size:12px; word-break:break-all; color:#6b7280;">{{ $resetUrl }}</p>
@endsection
