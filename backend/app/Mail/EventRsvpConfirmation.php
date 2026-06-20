<?php

namespace App\Mail;

use App\Models\CMS\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EventRsvpConfirmation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Event $event,
        public string $registrantName,
        public string $registrantEmail,
        public string $studentId,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'RSVP Confirmed: '.$this->event->title,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.event_rsvp_confirmation',
        );
    }
}
