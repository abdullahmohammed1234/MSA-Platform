<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactFormSubmission extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $senderName,
        public string $senderEmail,
        public string $subjectLine,
        public string $messageBody,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'SFU MSA Contact: '.$this->subjectLine,
            replyTo: [
                new Address($this->senderEmail, $this->senderName),
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.contact_form',
        );
    }
}
