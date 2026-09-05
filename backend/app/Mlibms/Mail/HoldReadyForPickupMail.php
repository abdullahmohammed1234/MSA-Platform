<?php

namespace App\Mlibms\Mail;

use App\Mlibms\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class HoldReadyForPickupMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Reservation $reservation
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Hold Ready for Pickup: '{$this->reservation->book->title}'",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.mlibms.hold_ready',
        );
    }
}
