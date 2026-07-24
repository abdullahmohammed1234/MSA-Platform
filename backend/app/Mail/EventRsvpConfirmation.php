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
use Throwable;

class EventRsvpConfirmation extends Mailable
{
    use Queueable, SerializesModels;

    /** @var string|null Raw PNG bytes for CID embedding in the email body. */
    public ?string $qrPngBinary = null;

    public function __construct(
        public Event $event,
        public EventRegistration $registration,
        public string $registrantName,
        public string $registrantEmail,
        public string $registrantPhone,
        public string $checkInCode,
    ) {
        try {
            $binary = app(EventCheckInQrService::class)->pngBinary($registration->uuid);
            // Gmail only reliably displays PNG/JPEG inline — skip SVG fallbacks.
            if (is_string($binary) && str_starts_with($binary, "\x89PNG")) {
                $this->qrPngBinary = $binary;
            }
        } catch (Throwable) {
            $this->qrPngBinary = null;
        }
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
        if (! $this->qrPngBinary) {
            return [];
        }

        return [
            Attachment::fromData(
                fn () => $this->qrPngBinary,
                'event-checkin-qr.png'
            )->withMime('image/png'),
        ];
    }
}
