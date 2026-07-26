<?php

namespace Database\Seeders\Ems;

use App\Ems\Enums\NotificationType;
use App\Ems\Models\EmailTemplate;
use Illuminate\Database\Seeder;

class EmsEmailTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $placeholders = [
            'attendee_name', 'event_name', 'event_date', 'event_time', 'event_location',
            'ticket_type', 'registration_number', 'ticket_number', 'qr_code',
            'ticket_download_link', 'event_details_link', 'order_number',
            'amount_paid', 'currency', 'payment_reference', 'square_transaction_reference',
            'refund_amount', 'change_summary', 'feedback_link', 'organizer_name',
            'cancellation_reason',
        ];

        foreach ($this->templates() as $row) {
            EmailTemplate::query()->updateOrCreate(
                ['key' => $row['key']],
                array_merge($row, [
                    'placeholders' => $placeholders,
                    'is_active' => true,
                    'is_system' => true,
                ])
            );
        }
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function templates(): array
    {
        return [
            [
                'key' => NotificationType::RegistrationConfirmed->value,
                'name' => 'Registration Confirmation',
                'category' => 'registration',
                'subject' => 'Registration confirmed — {{ event_name }}',
                'body_html' => '<p>Assalamu alaikum {{ attendee_name }},</p><p>Your registration <strong>{{ registration_number }}</strong> for <strong>{{ event_name }}</strong> is confirmed.</p><p>Date: {{ event_date }}<br>Time: {{ event_time }}<br>Location: {{ event_location }}<br>Ticket: {{ ticket_type }}</p><p><a href="{{ ticket_download_link }}">Download your ticket</a> · <a href="{{ event_details_link }}">Event details</a></p><p><img src="{{ qr_code }}" alt="QR code" width="180" height="180" /></p>',
                'body_text' => "Assalamu alaikum {{ attendee_name }},\n\nYour registration {{ registration_number }} for {{ event_name }} is confirmed.\nDate: {{ event_date }}\nTime: {{ event_time }}\nLocation: {{ event_location }}\nTicket: {{ ticket_download_link }}",
            ],
            [
                'key' => NotificationType::TicketEmail->value,
                'name' => 'Ticket Email',
                'category' => 'tickets',
                'subject' => 'Your ticket — {{ event_name }}',
                'body_html' => '<p>Assalamu alaikum {{ attendee_name }},</p><p>Here is your ticket for <strong>{{ event_name }}</strong>.</p><p>Ticket number: {{ ticket_number }}<br>Type: {{ ticket_type }}</p><p><a href="{{ ticket_download_link }}">View / download ticket</a></p><p><img src="{{ qr_code }}" alt="QR code" width="180" height="180" /></p>',
                'body_text' => "Your ticket {{ ticket_number }} for {{ event_name }}.\nDownload: {{ ticket_download_link }}",
            ],
            [
                'key' => NotificationType::QrCodeEmail->value,
                'name' => 'QR Code Email',
                'category' => 'tickets',
                'subject' => 'Your QR code — {{ event_name }}',
                'body_html' => '<p>Assalamu alaikum {{ attendee_name }},</p><p>Present this QR code at check-in for <strong>{{ event_name }}</strong>.</p><p><img src="{{ qr_code }}" alt="Check-in QR" width="220" height="220" style="max-width:100%;height:auto;" /></p><p>Ticket: {{ ticket_number }}</p>',
                'body_text' => "Your QR code for {{ event_name }}: {{ qr_code }}\nTicket: {{ ticket_number }}",
            ],
            [
                'key' => NotificationType::TicketReissue->value,
                'name' => 'Ticket Reissue',
                'category' => 'tickets',
                'subject' => 'Ticket reissued — {{ event_name }}',
                'body_html' => '<p>Assalamu alaikum {{ attendee_name }},</p><p>Your ticket for <strong>{{ event_name }}</strong> has been reissued.</p><p>Ticket: {{ ticket_number }}</p><p><a href="{{ ticket_download_link }}">Download ticket</a></p><p><img src="{{ qr_code }}" alt="QR code" width="180" height="180" /></p>',
                'body_text' => "Your ticket for {{ event_name }} was reissued. {{ ticket_download_link }}",
            ],
            [
                'key' => NotificationType::PaymentConfirmation->value,
                'name' => 'Payment Confirmation',
                'category' => 'payments',
                'subject' => 'Payment received — {{ event_name }}',
                'body_html' => '<p>Assalamu alaikum {{ attendee_name }},</p><p>We received your payment for <strong>{{ event_name }}</strong>.</p><p>Amount: {{ amount_paid }} {{ currency }}<br>Order: {{ order_number }}<br>Payment reference: {{ payment_reference }}<br>Square reference: {{ square_transaction_reference }}</p>',
                'body_text' => "Payment of {{ amount_paid }} {{ currency }} received for {{ event_name }}. Order {{ order_number }}.",
            ],
            [
                'key' => NotificationType::PaymentFailure->value,
                'name' => 'Payment Failure',
                'category' => 'payments',
                'subject' => 'Payment unsuccessful — {{ event_name }}',
                'body_html' => '<p>Assalamu alaikum {{ attendee_name }},</p><p>Your payment for <strong>{{ event_name }}</strong> was not completed. Your registration has not been confirmed.</p><p><a href="{{ event_details_link }}">Try again</a></p>',
                'body_text' => "Payment for {{ event_name }} was unsuccessful. Visit {{ event_details_link }} to try again.",
            ],
            [
                'key' => NotificationType::EventReminder->value,
                'name' => 'Event Reminder',
                'category' => 'reminder',
                'subject' => 'Reminder: {{ event_name }}',
                'body_html' => '<p>Assalamu alaikum {{ attendee_name }},</p><p>This is a reminder that <strong>{{ event_name }}</strong> is coming up.</p><p>Date: {{ event_date }}<br>Time: {{ event_time }}<br>Location: {{ event_location }}</p><p><a href="{{ ticket_download_link }}">Your ticket</a></p>',
                'body_text' => "Reminder: {{ event_name }} on {{ event_date }} at {{ event_time }}. Location: {{ event_location }}.",
            ],
            [
                'key' => NotificationType::EventUpdated->value,
                'name' => 'Event Update',
                'category' => 'update',
                'subject' => 'Update: {{ event_name }}',
                'body_html' => '<p>Assalamu alaikum {{ attendee_name }},</p><p><strong>{{ event_name }}</strong> has been updated.</p><p>{{ change_summary }}</p><p>Date: {{ event_date }} · {{ event_time }}<br>Location: {{ event_location }}</p><p><a href="{{ event_details_link }}">View details</a></p>',
                'body_text' => "{{ event_name }} updated. {{ change_summary }}",
            ],
            [
                'key' => NotificationType::EventCancelled->value,
                'name' => 'Event Cancellation',
                'category' => 'cancellation',
                'subject' => 'Cancelled: {{ event_name }}',
                'body_html' => '<p>Assalamu alaikum {{ attendee_name }},</p><p><strong>{{ event_name }}</strong> has been cancelled.</p><p>{{ cancellation_reason }}</p><p>If you paid for this event, a refund workflow has been initiated where applicable. You will receive a separate refund notice.</p>',
                'body_text' => "{{ event_name }} has been cancelled. {{ cancellation_reason }}",
            ],
            [
                'key' => NotificationType::RegistrationCancelled->value,
                'name' => 'Registration Cancellation',
                'category' => 'cancellation',
                'subject' => 'Registration cancelled — {{ event_name }}',
                'body_html' => '<p>Assalamu alaikum {{ attendee_name }},</p><p>Your registration {{ registration_number }} for <strong>{{ event_name }}</strong> has been cancelled.</p>',
                'body_text' => "Your registration {{ registration_number }} for {{ event_name }} was cancelled.",
            ],
            [
                'key' => NotificationType::WaitlistConfirmed->value,
                'name' => 'Waitlist Confirmation',
                'category' => 'registration',
                'subject' => 'Waitlist confirmed — {{ event_name }}',
                'body_html' => '<p>Assalamu alaikum {{ attendee_name }},</p><p>You have been added to the waitlist for <strong>{{ event_name }}</strong>. We will notify you if a spot opens.</p>',
                'body_text' => "You are on the waitlist for {{ event_name }}.",
            ],
            [
                'key' => NotificationType::WaitlistPromoted->value,
                'name' => 'Waitlist Promotion',
                'category' => 'registration',
                'subject' => 'A spot opened — {{ event_name }}',
                'body_html' => '<p>Assalamu alaikum {{ attendee_name }},</p><p>A spot is available for <strong>{{ event_name }}</strong>. Please complete your registration soon.</p><p><a href="{{ event_details_link }}">Register now</a></p>',
                'body_text' => "A spot opened for {{ event_name }}. Register at {{ event_details_link }}.",
            ],
            [
                'key' => NotificationType::WaitlistRemoved->value,
                'name' => 'Waitlist Removal',
                'category' => 'registration',
                'subject' => 'Removed from waitlist — {{ event_name }}',
                'body_html' => '<p>Assalamu alaikum {{ attendee_name }},</p><p>You have been removed from the waitlist for <strong>{{ event_name }}</strong>.</p>',
                'body_text' => "You were removed from the waitlist for {{ event_name }}.",
            ],
            [
                'key' => NotificationType::RefundInitiated->value,
                'name' => 'Refund Initiated',
                'category' => 'payments',
                'subject' => 'Refund initiated — {{ event_name }}',
                'body_html' => '<p>Assalamu alaikum {{ attendee_name }},</p><p>A refund of {{ refund_amount }} {{ currency }} has been initiated for order {{ order_number }} ({{ event_name }}).</p>',
                'body_text' => "Refund of {{ refund_amount }} {{ currency }} initiated for {{ event_name }}.",
            ],
            [
                'key' => NotificationType::RefundCompleted->value,
                'name' => 'Refund Completed',
                'category' => 'payments',
                'subject' => 'Refund completed — {{ event_name }}',
                'body_html' => '<p>Assalamu alaikum {{ attendee_name }},</p><p>Your refund of {{ refund_amount }} {{ currency }} for {{ event_name }} is complete.</p>',
                'body_text' => "Refund of {{ refund_amount }} {{ currency }} completed for {{ event_name }}.",
            ],
            [
                'key' => NotificationType::RefundFailed->value,
                'name' => 'Refund Failed',
                'category' => 'payments',
                'subject' => 'Refund issue — {{ event_name }}',
                'body_html' => '<p>Assalamu alaikum {{ attendee_name }},</p><p>We could not complete your refund for {{ event_name }}. Our team will follow up.</p>',
                'body_text' => "Refund for {{ event_name }} could not be completed. We will follow up.",
            ],
            [
                'key' => NotificationType::ThankYou->value,
                'name' => 'Thank You',
                'category' => 'post_event',
                'subject' => 'Thank you for attending {{ event_name }}',
                'body_html' => '<p>Assalamu alaikum {{ attendee_name }},</p><p>JazakAllahu khairan for attending <strong>{{ event_name }}</strong>. We hope it benefited you.</p>',
                'body_text' => "Thank you for attending {{ event_name }}.",
            ],
            [
                'key' => NotificationType::FeedbackRequest->value,
                'name' => 'Feedback Request',
                'category' => 'post_event',
                'subject' => 'Share your feedback — {{ event_name }}',
                'body_html' => '<p>Assalamu alaikum {{ attendee_name }},</p><p>We would love your feedback on <strong>{{ event_name }}</strong>.</p><p><a href="{{ feedback_link }}">Leave feedback</a></p><p>— {{ organizer_name }}</p>',
                'body_text' => "Please share feedback for {{ event_name }}: {{ feedback_link }}",
            ],
            [
                'key' => NotificationType::EventRecap->value,
                'name' => 'Event Recap',
                'category' => 'post_event',
                'subject' => 'Recap — {{ event_name }}',
                'body_html' => '<p>Assalamu alaikum {{ attendee_name }},</p><p>Here is a short recap of <strong>{{ event_name }}</strong>.</p><p>{{ change_summary }}</p>',
                'body_text' => "Recap of {{ event_name }}: {{ change_summary }}",
            ],
            [
                'key' => NotificationType::CertificateAvailable->value,
                'name' => 'Certificate Available',
                'category' => 'post_event',
                'subject' => 'Certificate available — {{ event_name }}',
                'body_html' => '<p>Assalamu alaikum {{ attendee_name }},</p><p>A certificate for <strong>{{ event_name }}</strong> is now available. Certificate generation details will follow in a future release.</p>',
                'body_text' => "A certificate for {{ event_name }} is available (foundation notice).",
            ],
            [
                'key' => NotificationType::EventStartingSoon->value,
                'name' => 'Event Starting Soon',
                'category' => 'reminder',
                'subject' => 'Starting soon — {{ event_name }}',
                'body_html' => '<p>Assalamu alaikum {{ attendee_name }},</p><p><strong>{{ event_name }}</strong> is starting soon ({{ event_time }}).</p><p>Location: {{ event_location }}</p>',
                'body_text' => "{{ event_name }} is starting soon at {{ event_time }}.",
            ],
            [
                'key' => NotificationType::EventStartingNow->value,
                'name' => 'Event Starting Now',
                'category' => 'reminder',
                'subject' => 'Starting now — {{ event_name }}',
                'body_html' => '<p>Assalamu alaikum {{ attendee_name }},</p><p><strong>{{ event_name }}</strong> is starting now.</p>',
                'body_text' => "{{ event_name }} is starting now.",
            ],
        ];
    }
}
