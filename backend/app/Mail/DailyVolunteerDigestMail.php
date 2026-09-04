<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DailyVolunteerDigestMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param Collection<int, \App\Models\VolunteerRegistration> $registrations
     */
    public function __construct(
        public Collection $registrations,
        public string $dateStr
    ) {}

    public function envelope(): Envelope
    {
        $count = $this->registrations->count();
        return new Envelope(
            subject: "SFU MSA Daily Volunteer Registrations Summary ({$count} new - {$this->dateStr})",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.daily_volunteer_digest',
        );
    }
}
