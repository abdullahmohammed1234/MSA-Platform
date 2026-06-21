<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewsletterAnnouncement extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $subscriberEmail,
        public string $announcementTitle,
        public string $announcementExcerpt,
        public string $announcementUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'SFU MSA Update: '.$this->announcementTitle,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.newsletter_announcement',
        );
    }
}
