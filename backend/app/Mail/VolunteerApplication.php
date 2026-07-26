<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VolunteerApplication extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $name,
        public string $email,
        public string $studentNumber,
        public string $department,
        public string $interests,
        public ?string $experience = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'SFU MSA Volunteer Application: '.$this->name,
            replyTo: [
                new Address($this->email, $this->name),
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.volunteer_application',
        );
    }
}
