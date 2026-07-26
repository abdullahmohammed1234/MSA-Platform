<?php

namespace App\Ems\Enums;

/**
 * Canonical EMS outbound notification types.
 *
 * Transactional types always deliver regardless of marketing preferences.
 */
enum NotificationType: string
{
    // Registration
    case RegistrationConfirmed = 'registration_confirmed';
    case RegistrationCancelled = 'registration_cancelled';
    case WaitlistConfirmed = 'waitlist_confirmed';
    case WaitlistRemoved = 'waitlist_removed';
    case WaitlistPromoted = 'waitlist_promoted';

    // Tickets
    case TicketEmail = 'ticket_email';
    case QrCodeEmail = 'qr_code_email';
    case TicketReissue = 'ticket_reissue';

    // Payments
    case PaymentConfirmation = 'payment_confirmation';
    case PaymentFailure = 'payment_failure';
    case RefundInitiated = 'refund_initiated';
    case RefundCompleted = 'refund_completed';
    case RefundFailed = 'refund_failed';

    // Reminders
    case EventReminder = 'event_reminder';
    case EventStartingSoon = 'event_starting_soon';
    case EventStartingNow = 'event_starting_now';

    // Updates / cancellation
    case EventUpdated = 'event_updated';
    case EventCancelled = 'event_cancelled';

    // Post-event
    case ThankYou = 'thank_you';
    case FeedbackRequest = 'feedback_request';
    case EventRecap = 'event_recap';
    case CertificateAvailable = 'certificate_available';

    public function label(): string
    {
        return match ($this) {
            self::RegistrationConfirmed => 'Registration confirmation',
            self::RegistrationCancelled => 'Registration cancellation',
            self::WaitlistConfirmed => 'Waitlist confirmation',
            self::WaitlistRemoved => 'Waitlist removal',
            self::WaitlistPromoted => 'Waitlist promotion',
            self::TicketEmail => 'Ticket email',
            self::QrCodeEmail => 'QR code email',
            self::TicketReissue => 'Ticket reissue',
            self::PaymentConfirmation => 'Payment confirmation',
            self::PaymentFailure => 'Payment failure',
            self::RefundInitiated => 'Refund initiated',
            self::RefundCompleted => 'Refund completed',
            self::RefundFailed => 'Refund failed',
            self::EventReminder => 'Event reminder',
            self::EventStartingSoon => 'Event starting soon',
            self::EventStartingNow => 'Event starting now',
            self::EventUpdated => 'Event update',
            self::EventCancelled => 'Event cancelled',
            self::ThankYou => 'Thank you',
            self::FeedbackRequest => 'Feedback request',
            self::EventRecap => 'Event recap',
            self::CertificateAvailable => 'Certificate available',
        };
    }

    public function category(): string
    {
        return match ($this) {
            self::RegistrationConfirmed,
            self::RegistrationCancelled,
            self::WaitlistConfirmed,
            self::WaitlistRemoved,
            self::WaitlistPromoted => 'registration',
            self::TicketEmail,
            self::QrCodeEmail,
            self::TicketReissue => 'tickets',
            self::PaymentConfirmation,
            self::PaymentFailure,
            self::RefundInitiated,
            self::RefundCompleted,
            self::RefundFailed => 'payments',
            self::EventReminder,
            self::EventStartingSoon,
            self::EventStartingNow => 'reminder',
            self::EventUpdated => 'update',
            self::EventCancelled => 'cancellation',
            self::ThankYou,
            self::FeedbackRequest,
            self::EventRecap,
            self::CertificateAvailable => 'post_event',
        };
    }

    /**
     * Transactional emails always send; preference-gated types may be skipped.
     */
    public function isTransactional(): bool
    {
        return match ($this) {
            self::EventReminder,
            self::EventStartingSoon,
            self::EventStartingNow,
            self::EventUpdated,
            self::ThankYou,
            self::FeedbackRequest,
            self::EventRecap,
            self::CertificateAvailable => false,
            default => true,
        };
    }

    /**
     * Preference column on ems_notification_preferences, when preference-gated.
     */
    public function preferenceKey(): ?string
    {
        return match ($this) {
            self::EventReminder,
            self::EventStartingSoon,
            self::EventStartingNow => 'event_reminders',
            self::EventUpdated => 'event_updates',
            self::FeedbackRequest => 'feedback_requests',
            self::ThankYou,
            self::EventRecap,
            self::CertificateAvailable => 'post_event',
            default => null,
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Types organizers may manually resend.
     *
     * @return array<int, self>
     */
    public static function resendable(): array
    {
        return [
            self::RegistrationConfirmed,
            self::TicketEmail,
            self::QrCodeEmail,
            self::TicketReissue,
            self::PaymentConfirmation,
            self::EventReminder,
            self::EventCancelled,
            self::RegistrationCancelled,
        ];
    }
}
