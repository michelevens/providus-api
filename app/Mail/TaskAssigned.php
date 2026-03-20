<?php

namespace App\Mail;

use App\Models\Agency;
use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TaskAssigned extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Task $task,
        public Agency $agency,
        public string $assigneeName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "New Task: {$this->task->title}",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.task-assigned');
    }
}
