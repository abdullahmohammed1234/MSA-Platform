<?php

namespace App\Ems\Mail;

use App\Ems\Models\EventNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Generic EMS notification mailable. Content is pre-rendered into the ledger row.
 */
class EventNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly EventNotification $notification)
    {
    }

    public function envelope(): Envelope
    {
        $fromAddress = (string) config('ems.notifications.from_address', config('mail.from.address'));
        $fromName = (string) config('ems.notifications.from_name', config('mail.from.name'));

        $bccAddress = config('ems.notifications.bcc_archive_address');
        $bcc = null;
        if ($bccAddress && $this->notification->type === 'registration_confirmed') {
            $bcc = [$bccAddress];
        }

        return new Envelope(
            from: new Address($fromAddress, $fromName),
            bcc: $bcc,
            subject: (string) ($this->notification->subject ?: 'MSA Event Notification'),
        );
    }

    public function content(): Content
    {
        $payload = $this->notification->payload ?? [];

        return new Content(
            view: 'emails.ems.notification',
            with: [
                'subject' => (string) ($this->notification->subject ?: 'MSA Event Notification'),
                'bodyHtml' => (string) ($payload['body_html'] ?? $this->notification->body ?? ''),
                'bodyText' => (string) ($payload['body_text'] ?? strip_tags((string) ($this->notification->body ?? ''))),
                'fromName' => (string) config('ems.notifications.from_name', 'SFU MSA Events'),
            ],
            text: 'emails.ems.notification_text',
        );
    }
}
