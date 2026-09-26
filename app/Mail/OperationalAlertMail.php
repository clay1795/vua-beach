<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class OperationalAlertMail extends Mailable
{
    /** @param array<string, int|string|null> $details */
    public function __construct(
        public string $event,
        public string $job,
        public string $exceptionClass,
        public array $details = [],
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: '[Vua Beach] Cảnh báo vận hành cần xử lý');
    }

    public function content(): Content
    {
        return new Content(text: 'emails.operational-alert');
    }
}
