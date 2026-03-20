@extends('emails.layout')

@section('title', 'Welcome to Credentik')

@section('content')
<h2>Welcome to {{ $agency->name }}!</h2>

<p>Hi {{ $user->first_name }},</p>

<p>Your account has been created successfully. You're all set to start managing your credentialing and licensing operations.</p>

<div class="success-box">
    <strong>Your account is active.</strong> You can log in at any time using the email and password you chose during registration.
</div>

<div class="details">
    <div class="detail-row">
        <span class="detail-label">Organization</span>
        <span class="detail-value">{{ $agency->name }}</span>
    </div>
    <div class="detail-row">
        <span class="detail-label">Email</span>
        <span class="detail-value">{{ $user->email }}</span>
    </div>
    <div class="detail-row">
        <span class="detail-label">Role</span>
        <span class="detail-value">{{ ucfirst($user->role) }}</span>
    </div>
</div>

<p>Here's what you can do next:</p>
<ul style="color:#374151;font-size:14px;line-height:2;padding-left:20px;">
    <li>Add your providers and their credentials</li>
    <li>Track license expirations and renewals</li>
    <li>Submit and monitor credentialing applications</li>
    <li>Generate compliance reports</li>
</ul>

<div style="text-align:center;">
    <a href="{{ config('app.frontend_url', 'https://app.credentik.com') }}" class="btn btn-primary">Go to Dashboard</a>
</div>
@endsection
