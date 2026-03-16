<?php

namespace App\Mail;

use App\Models\Agency;
use App\Models\Testimonial;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TestimonialRequest extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Testimonial $testimonial,
        public Agency $agency,
        public string $reviewUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "{$this->agency->name} — We'd love your feedback",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.testimonial-request',
        );
    }
}
