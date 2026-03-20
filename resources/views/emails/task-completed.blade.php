@extends('emails.layout')

@section('title', 'Task Completed')

@section('content')
<h2>Task Completed</h2>

<p>A task has been marked as complete.</p>

<div class="success-box">
    <strong>✓ Completed.</strong> This task has been finished and closed.
</div>

<div class="details">
    <div class="detail-row">
        <span class="detail-label">Task</span>
        <span class="detail-value">{{ $task->title }}</span>
    </div>
    @if($completedBy)
    <div class="detail-row">
        <span class="detail-label">Completed By</span>
        <span class="detail-value">{{ $completedBy }}</span>
    </div>
    @endif
    <div class="detail-row">
        <span class="detail-label">Completed On</span>
        <span class="detail-value">{{ now()->format('F j, Y') }}</span>
    </div>
</div>

<div style="text-align:center;">
    <a href="{{ config('app.frontend_url', 'https://app.credentik.com') }}/#tasks" class="btn btn-outline">View Tasks</a>
</div>
@endsection
