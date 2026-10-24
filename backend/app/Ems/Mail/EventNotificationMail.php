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

        $bodyHtml = (string) ($payload['body_html'] ?? $this->notification->body ?? '');
        $bodyText = (string) ($payload['body_text'] ?? strip_tags((string) ($this->notification->body ?? '')));
        $subject = (string) ($this->notification->subject ?: 'MSA Event Notification');

        if ($this->notification->event_id !== null && ! empty($this->notification->template_key)) {
            $event = $this->notification->event ?? \App\Ems\Models\Event::find($this->notification->event_id);
            if ($event !== null && $this->notification->created_at !== null && $event->updated_at?->gt($this->notification->created_at)) {
                $registration = $this->notification->registration;
                if ($registration !== null) {
                    $registration->loadMissing(['event.organizer', 'ticketType', 'tickets', 'order', 'settledPayment', 'user']);
                }
                $renderer = app(\App\Ems\Services\Notifications\TemplateRenderer::class);
                $fresh = $renderer->render(
                    $this->notification->template_key,
                    $this->notification->payload ?? [],
                    $registration
                );
                $bodyHtml = $fresh['body_html'];
                $bodyText = $fresh['body_text'];
                $subject = $fresh['subject'];
            }
        }

        return new Content(
            view: 'emails.ems.notification',
            with: [
                'subject' => $subject,
                'bodyHtml' => $bodyHtml,
                'bodyText' => $bodyText,
                'fromName' => (string) config('ems.notifications.from_name', 'SFU MSA Events'),
            ],
            text: 'emails.ems.notification_text',
        );
    }
}
