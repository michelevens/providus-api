@extends('emails.layout')

@section('title', 'Complete Your Provider Profile')

@section('content')
<h2>Complete Your Provider Profile</h2>

<p>Hi there,</p>

<p><strong>{{ $agency->name }}</strong> has invited you to complete your provider profile on Credentik. This information is needed for your credentialing process.</p>

<div class="info-box">
    <strong>What you'll need:</strong> Your NPI number, license information, education history, malpractice insurance details, and work history.
</div>

<div style="text-align:center;">
    <a href="{{ $onboardUrl }}" class="btn btn-primary">Complete Your Profile</a>
</div>

<div class="alert-box">
    <strong>This link expires in {{ $expiresInDays ?? 7 }} days.</strong> If you need a new link, contact your credentialing specialist.
</div>

<p style="font-size:13px; color:#6b7280;">If you're having trouble clicking the button, copy and paste this URL:</p>
<p style="font-size:12px; word-break:break-all; color:#6b7280;">{{ $onboardUrl }}</p>
@endsection
