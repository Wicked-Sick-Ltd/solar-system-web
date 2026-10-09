<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

final class SiteFeedback extends Mailable
{
    public function __construct(
        public readonly string $category,
        public readonly string $feedbackText,
        public readonly ?string $replyEmail,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: $this->replyEmail ? [new Address($this->replyEmail)] : [],
            subject: '[Public Universe] '.$this->category,
        );
    }

    public function content(): Content
    {
        return new Content(text: 'mail.site-feedback');
    }
}
