<?php

namespace App\Ems\Mail;

use App\Ems\Models\EventNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RegistrationEmailFailedAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly EventNotification $notification,
        public readonly string $errorMessage
    ) {}

    public function envelope(): Envelope
    {
        $fromAddress = (string) config('ems.notifications.from_address', config('mail.from.address'));
        $fromName = (string) config('ems.notifications.from_name', config('mail.from.name'));

        return new Envelope(
            from: new Address($fromAddress, $fromName),
            subject: "ALERT: Registration Email Failed — " . ($this->notification->event?->name ?? 'Event'),
        );
    }

    public function content(): Content
    {
        $registration = $this->notification->registration;
        $event = $this->notification->event;
        $payment = $registration?->settledPayment;

        // Construct admin portal URL using ems configuration or fallback
        $baseUrl = rtrim((string) config('ems.frontend_url', config('app.url') . '/ems'), '/');
        $adminCommunicationsUrl = $event 
            ? "{$baseUrl}/events/{$event->uuid}/communications"
            : "{$baseUrl}/communications";

        return new Content(
            view: 'emails.ems.admin_failure_alert',
            with: [
                'eventName' => (string) ($event?->name ?? 'N/A'),
                'registrationRef' => (string) ($registration?->reference ?? 'N/A'),
                'attendeeName' => (string) ($registration?->attendee_name ?? 'N/A'),
                'attendeeEmail' => (string) ($registration?->attendee_email ?? 'N/A'),
                'notificationUuid' => (string) ($this->notification->uuid ?? 'N/A'),
                'notificationType' => (string) ($this->notification->type ?? 'N/A'),
                'paymentStatus' => (string) ($payment?->status?->value ?? 'N/A'),
                'paymentAmount' => $payment ? number_format((float) $payment->amount, 2) : 'N/A',
                'failureTimestamp' => now()->format('Y-m-d H:i:s'),
                'retryCount' => (int) $this->notification->retry_count,
                'errorMessage' => $this->errorMessage,
                'adminCommunicationsUrl' => $adminCommunicationsUrl,
            ]
        );
    }
}
