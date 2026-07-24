<?php

namespace App\Mail;

use App\Models\CMS\Event;
use App\Models\CMS\EventRegistration;
use App\Services\CMS\EventCheckInQrService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EventRsvpConfirmation extends Mailable
{
    use Queueable, SerializesModels;

    public string $qrDataUri;

    public function __construct(
        public Event $event,
        public EventRegistration $registration,
        public string $registrantName,
        public string $registrantEmail,
        public string $registrantPhone,
        public string $checkInCode,
    ) {
        $this->qrDataUri = app(EventCheckInQrService::class)->dataUri($registration->uuid);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(
                config('mail.from.address', 'no_reply@sfu.ca'),
                config('mail.from.name', 'SFU MSA')
            ),
            subject: 'Registration Confirmed: '.$this->event->title,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.event_rsvp_confirmation',
        );
    }

    public function attachments(): array
    {
        $qrService = app(EventCheckInQrService::class);

        return [
            Attachment::fromData(
                fn () => $qrService->pngBinary($this->registration->uuid),
                $qrService->attachmentFilename($this->registration->uuid)
            )->withMime($qrService->attachmentMime($this->registration->uuid)),
        ];
    }
}
