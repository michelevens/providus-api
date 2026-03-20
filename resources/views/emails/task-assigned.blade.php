@extends('emails.layout')

@section('title', 'New Task Assigned')

@section('content')
<h2>New Task Assigned</h2>

<p>Hi {{ $assigneeName }},</p>

<p>A new task has been assigned to you.</p>

<div class="details">
    <div class="detail-row">
        <span class="detail-label">Task</span>
        <span class="detail-value">{{ $task->title }}</span>
    </div>
    @if($task->description)
    <div class="detail-row">
        <span class="detail-label">Description</span>
        <span class="detail-value">{{ \Illuminate\Support\Str::limit($task->description, 80) }}</span>
    </div>
    @endif
    @if($task->due_date)
    <div class="detail-row">
        <span class="detail-label">Due Date</span>
        <span class="detail-value" style="color:#d97706;font-weight:700;">{{ $task->due_date->format('F j, Y') }}</span>
    </div>
    @endif
    @if($task->priority)
    <div class="detail-row">
        <span class="detail-label">Priority</span>
        <span class="detail-value">{{ ucfirst($task->priority) }}</span>
    </div>
    @endif
</div>

<div style="text-align:center;">
    <a href="{{ config('app.frontend_url', 'https://app.credentik.com') }}/#tasks" class="btn btn-primary">View Task</a>
</div>
@endsection
