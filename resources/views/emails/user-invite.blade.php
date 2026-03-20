@extends('emails.layout')

@section('title', "You're invited to {{ $agency->name }}")

@section('content')
<h2>You're Invited!</h2>

<p>Hi {{ $user->first_name }},</p>

<p>You've been invited to join <strong>{{ $agency->name }}</strong> on Credentik.</p>

<div class="details">
    <div class="detail-row">
        <span class="detail-label">Organization</span>
        <span class="detail-value">{{ $agency->name }}</span>
    </div>
    <div class="detail-row">
        <span class="detail-label">Your Role</span>
        <span class="detail-value">{{ ucfirst($user->role) }}</span>
    </div>
    <div class="detail-row">
        <span class="detail-label">Email</span>
        <span class="detail-value">{{ $user->email }}</span>
    </div>
</div>

<p>Click the button below to accept the invitation and set up your account.</p>

<a href="{{ $inviteUrl }}" class="btn btn-primary">Accept Invitation</a>

<div class="alert-box">
    <strong>This invitation expires in 7 days.</strong> If you did not expect this email, you can safely ignore it.
</div>

<p style="font-size:13px; color:#6b7280;">If you're having trouble clicking the button, copy and paste this URL into your browser:</p>
<p style="font-size:12px; word-break:break-all; color:#6b7280;">{{ $inviteUrl }}</p>
@endsection
