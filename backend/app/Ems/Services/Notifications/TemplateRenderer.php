<?php

namespace App\Ems\Services\Notifications;

use App\Ems\Enums\NotificationType;
use App\Ems\Models\EmailTemplate;
use App\Ems\Models\Event;
use App\Ems\Models\Order;
use App\Ems\Models\Payment;
use App\Ems\Models\Registration;
use App\Ems\Models\Ticket;

/**
 * Renders EMS email templates with {{ placeholder }} substitution.
 */
class TemplateRenderer
{
    /**
     * @param  array<string, mixed>  $extra
     * @return array{subject: string, body_html: string, body_text: string, template_key: string}
     */
    public function render(string $templateKey, array $extra = [], ?Registration $registration = null): array
    {
        $template = EmailTemplate::query()
            ->where('key', $templateKey)
            ->where('is_active', true)
            ->first();

        $defaults = $this->defaultsFor($templateKey);
        $subject = $template?->subject ?? $defaults['subject'];
        $bodyHtml = $template?->body_html ?? $defaults['body_html'];
        $bodyText = $template?->body_text ?? strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $bodyHtml));

        $vars = array_merge($this->buildContext($registration, $extra), $extra);

        return [
            'subject' => $this->replace($subject, $vars),
            'body_html' => $this->replace($bodyHtml, $vars),
            'body_text' => $this->replace($bodyText, $vars),
            'template_key' => $templateKey,
        ];
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, string>
     */
    public function buildContext(?Registration $registration = null, array $extra = []): array
    {
        $event = $registration?->event
            ?? (($extra['event'] ?? null) instanceof Event ? $extra['event'] : null);
        $order = $registration?->order
            ?? (($extra['order'] ?? null) instanceof Order ? $extra['order'] : null);
        $payment = (($extra['payment'] ?? null) instanceof Payment)
            ? $extra['payment']
            : $registration?->settledPayment;
        $tickets = $registration?->relationLoaded('tickets')
            ? $registration->tickets
            : ($registration?->tickets()->get() ?? collect());

        if (isset($extra['ticket_id'])) {
            $specificTicket = $tickets->firstWhere('id', $extra['ticket_id']);
            if ($specificTicket !== null) {
                $tickets = collect([$specificTicket]);
            }
        }

        $ticketCodes = $tickets->pluck('code')->filter()->values()->all();
        $firstTicket = $tickets->first();
        $qrUrl = $firstTicket instanceof Ticket
            ? $this->ticketQrUrl($firstTicket)
            : '';
        $ticketUrl = $firstTicket instanceof Ticket
            ? $this->ticketUrl($firstTicket)
            : '';

        $signup = ($extra['signup'] ?? null) instanceof \App\Volunteering\Models\Signup ? $extra['signup'] : null;
        $opportunity = ($extra['opportunity'] ?? null) instanceof \App\Volunteering\Models\Opportunity ? $extra['opportunity'] : $signup?->opportunity;
        $shift = ($extra['shift'] ?? null) instanceof \App\Volunteering\Models\Shift ? $extra['shift'] : $signup?->shift;
        $team = ($extra['team'] ?? null) instanceof \App\Volunteering\Models\Team ? $extra['team'] : $signup?->team;

        $tz = (string) ($event?->timezone ?? $opportunity?->event?->timezone ?? config('ems.defaults.timezone', 'America/Vancouver'));

        $start = $event?->start_at?->timezone($tz);
        $shiftStart = $shift?->start_at?->timezone($tz) ?? $opportunity?->start_at?->timezone($tz);
        $shiftEnd = $shift?->end_at?->timezone($tz) ?? $opportunity?->end_at?->timezone($tz);

        $shiftDateFormatted = $shiftStart?->format('l, F j, Y') ?? $start?->format('l, F j, Y') ?? '';
        $shiftTimeFormatted = $shiftStart && $shiftEnd
            ? $shiftStart->format('g:i A') . ' – ' . $shiftEnd->format('g:i A T')
            : ($shiftStart?->format('g:i A T') ?? $start?->format('g:i A T') ?? '');

        $volunteerName = (string) ($extra['volunteer_name'] ?? $signup?->name ?? $extra['attendee_name'] ?? $registration?->attendee_name ?? '');
        $volunteerEmail = (string) ($extra['volunteer_email'] ?? $signup?->email ?? $extra['attendee_email'] ?? $registration?->attendee_email ?? '');

        $opportunityTitle = (string) ($opportunity?->title ?? $extra['opportunity_title'] ?? $event?->name ?? '');
        $teamName = (string) ($team?->name ?? $extra['team_name'] ?? '');
        $shiftName = (string) ($shift?->name ?? $extra['shift_name'] ?? '');
        $location = (string) ($shift?->location ?? $opportunity?->location ?? $event?->location ?? $extra['location'] ?? '');
        $instructions = (string) ($extra['instructions'] ?? $opportunity?->description ?? '');

        return [
            'attendee_name' => $volunteerName,
            'attendee_email' => $volunteerEmail,
            'volunteer_name' => $volunteerName,
            'volunteer_email' => $volunteerEmail,
            'event_name' => (string) ($event?->name ?? $opportunityTitle ?? ''),
            'event_date' => $shiftDateFormatted,
            'event_time' => $shiftTimeFormatted,
            'event_location' => $location,
            'event_timezone' => $tz,
            'opportunity_title' => $opportunityTitle,
            'team_name' => $teamName,
            'shift_name' => $shiftName,
            'shift_date' => $shiftDateFormatted,
            'shift_time' => $shiftTimeFormatted,
            'location' => $location,
            'instructions' => $instructions,
            'change_summary' => (string) ($extra['change_summary'] ?? ''),
            'promotion_deadline' => (string) ($extra['promotion_deadline'] ?? ''),
            'cancellation_reason' => (string) ($extra['cancellation_reason'] ?? $event?->cancellation_reason ?? ''),
            'ticket_type' => (string) ($registration?->ticketType?->name ?? $extra['ticket_type'] ?? ''),
            'registration_number' => (string) ($registration?->reference ?? ''),
            'ticket_number' => (string) ($firstTicket?->code ?? ''),
            'ticket_codes' => implode(', ', $ticketCodes),
            'ticket_count' => (string) $tickets->count(),
            'qr_code' => $qrUrl,
            'qr_code_url' => $qrUrl,
            'ticket_download_link' => $ticketUrl,
            'ticket_list_html' => $this->ticketListHtml($tickets),
            'ticket_list_text' => $this->ticketListText($tickets),
            'event_details_link' => $event ? $this->eventPublicUrl($event) : '',
            'feedback_link' => $event ? $this->feedbackUrl($event) : '',
            'order_number' => (string) ($order?->reference ?? ''),
            'payment_status' => (string) ($payment?->status?->value ?? ''),
            'amount_paid' => $payment ? number_format((float) $payment->amount, 2) : '',
            'currency' => (string) ($payment?->currency ?? $order?->currency ?? config('ems.defaults.currency', 'CAD')),
            'payment_reference' => (string) ($payment?->uuid ?? ''),
            'square_transaction_reference' => (string) ($payment?->provider_transaction_id ?? $payment?->provider_payment_id ?? ''),
            'refund_amount' => isset($extra['refund_amount']) ? number_format((float) $extra['refund_amount'], 2) : '',
            'organizer_name' => (string) ($event?->organizer_name ?? ($event?->organizer?->name ?? config('ems.notifications.from_name', 'SFU MSA Events'))),
        ];
    }

    /**
     * @param  array<string, string>  $vars
     */
    public function replace(string $content, array $vars): string
    {
        return (string) preg_replace_callback(
            '/\{\{\s*([a-z0-9_]+)\s*\}\}/i',
            function (array $matches) use ($vars): string {
                $key = strtolower($matches[1]);

                return array_key_exists($key, $vars) ? (string) $vars[$key] : $matches[0];
            },
            $content
        );
    }

    private function eventPublicUrl(Event $event): string
    {
        $base = rtrim((string) config('ems.public.frontend_url'), '/');

        return $base . '/events/' . $event->slug;
    }

    private function ticketUrl(Ticket $ticket): string
    {
        $base = rtrim((string) config('ems.public.frontend_url'), '/');

        return $base . '/tickets/' . $ticket->code;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, mixed>  $tickets
     */
    private function ticketListHtml($tickets): string
    {
        $html = '';
        foreach ($tickets as $ticket) {
            if (! $ticket instanceof Ticket) {
                continue;
            }
            $url = e($this->ticketUrl($ticket));
            $qr = e($this->ticketQrUrl($ticket));
            $code = e((string) $ticket->code);
            $html .= '<p style="margin:16px 0 8px;">Ticket <strong>'.$code.'</strong> — <a href="'.$url.'">View / download ticket</a></p>'
                .'<p style="margin:0 0 16px;"><img src="'.$qr.'" alt="QR code '.$code.'" width="180" height="180" /></p>';
        }

        return $html;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, mixed>  $tickets
     */
    private function ticketListText($tickets): string
    {
        $lines = [];
        foreach ($tickets as $ticket) {
            if (! $ticket instanceof Ticket) {
                continue;
            }
            $lines[] = 'Ticket '.$ticket->code.': '.$this->ticketUrl($ticket);
        }

        return implode("\n", $lines);
    }

    private function ticketQrUrl(Ticket $ticket): string
    {
        $base = rtrim((string) config('app.url'), '/');
        $prefix = trim((string) config('ems.route.prefix', 'api/v1/ems'), '/');

        return $base . '/' . $prefix . '/public/tickets/' . $ticket->code . '/qr';
    }

    private function feedbackUrl(Event $event): string
    {
        return $this->eventPublicUrl($event) . '/feedback';
    }

    /**
     * @return array{subject: string, body_html: string}
     */
    private function defaultsFor(string $key): array
    {
        if ($key === NotificationType::FeedbackRequest->value) {
            return [
                'subject' => 'How was {{ event_name }}? Share your feedback',
                'body_html' => '<p>Assalamu alaikum {{ attendee_name }},</p>'
                    . '<p>Thank you for attending <strong>{{ event_name }}</strong>. We would love to hear your feedback!</p>'
                    . '<p><a href="{{ feedback_link }}">Share your feedback</a></p>',
            ];
        }

        if ($key === NotificationType::VmsSignupConfirmed->value) {
            return [
                'subject' => 'Volunteer Signup Confirmed — {{ opportunity_title }}',
                'body_html' => '<p>Assalamu alaikum {{ volunteer_name }},</p>'
                    . '<p>Thank you for signing up to volunteer for <strong>{{ opportunity_title }}</strong>!</p>'
                    . '<p style="background:#fffbf4;border-left:4px solid #640c0e;padding:14px 16px;margin:16px 0;">'
                    . '<strong>Assignment Details:</strong><br>'
                    . 'Team: {{ team_name }}<br>'
                    . 'Shift: {{ shift_name }}<br>'
                    . 'Date: {{ shift_date }}<br>'
                    . 'Time: {{ shift_time }}<br>'
                    . 'Location: {{ location }}</p>'
                    . '<p>If you can no longer attend this shift, please cancel your signup as early as possible so waitlisted volunteers can take your spot.</p>',
            ];
        }

        if ($key === NotificationType::VmsWaitlistJoined->value) {
            return [
                'subject' => 'Waitlist Joined — {{ opportunity_title }}',
                'body_html' => '<p>Assalamu alaikum {{ volunteer_name }},</p>'
                    . '<p>You have been added to the waitlist for <strong>{{ opportunity_title }}</strong> (Shift: {{ shift_name }}).</p>'
                    . '<p>If capacity opens up, you will be automatically promoted and notified via email.</p>',
            ];
        }

        if ($key === NotificationType::VmsWaitlistPromoted->value) {
            return [
                'subject' => 'Waitlist Promotion: Volunteer Spot Confirmed — {{ opportunity_title }}',
                'body_html' => '<p>Assalamu alaikum {{ volunteer_name }},</p>'
                    . '<p>Great news! A volunteer spot has opened up and your signup for <strong>{{ opportunity_title }}</strong> is now confirmed!</p>'
                    . '<p style="background:#fffbf4;border-left:4px solid #640c0e;padding:14px 16px;margin:16px 0;">'
                    . '<strong>Shift Details:</strong><br>'
                    . 'Team: {{ team_name }}<br>'
                    . 'Shift: {{ shift_name }}<br>'
                    . 'Date: {{ shift_date }}<br>'
                    . 'Time: {{ shift_time }}<br>'
                    . 'Location: {{ location }}</p>',
            ];
        }

        if ($key === NotificationType::VmsShiftReminder->value) {
            return [
                'subject' => 'Reminder: Upcoming Volunteer Shift — {{ opportunity_title }}',
                'body_html' => '<p>Assalamu alaikum {{ volunteer_name }},</p>'
                    . '<p>This is a reminder for your upcoming volunteer shift for <strong>{{ opportunity_title }}</strong>.</p>'
                    . '<p style="background:#fffbf4;border-left:4px solid #640c0e;padding:14px 16px;margin:16px 0;">'
                    . '<strong>Shift Details:</strong><br>'
                    . 'Team: {{ team_name }}<br>'
                    . 'Shift: {{ shift_name }}<br>'
                    . 'Date: {{ shift_date }}<br>'
                    . 'Time: {{ shift_time }}<br>'
                    . 'Location: {{ location }}</p>'
                    . '<p>Jazakum Allahu Khairan for your dedication and support!</p>',
            ];
        }

        if ($key === NotificationType::VmsSignupCancelled->value) {
            return [
                'subject' => 'Volunteer Signup Cancelled — {{ opportunity_title }}',
                'body_html' => '<p>Assalamu alaikum {{ volunteer_name }},</p>'
                    . '<p>Your volunteer signup for <strong>{{ opportunity_title }}</strong> (Shift: {{ shift_name }}) has been cancelled.</p>'
                    . '<p>We hope to see you at future MSA events!</p>',
            ];
        }

        if ($key === NotificationType::VmsShiftUpdated->value) {
            return [
                'subject' => 'Important Shift Update — {{ opportunity_title }}',
                'body_html' => '<p>Assalamu alaikum {{ volunteer_name }},</p>'
                    . '<p>Your volunteer shift for <strong>{{ opportunity_title }}</strong> has been updated by administrators.</p>'
                    . '<p style="background:#fffbf4;border-left:4px solid #b02e32;padding:14px 16px;margin:16px 0;">'
                    . '<strong>Updates:</strong><br>{{ change_summary }}</p>'
                    . '<p><strong>Current Details:</strong><br>'
                    . 'Date: {{ shift_date }}<br>'
                    . 'Time: {{ shift_time }}<br>'
                    . 'Location: {{ location }}</p>',
            ];
        }

        if ($key === NotificationType::VmsOpportunityCancelled->value) {
            return [
                'subject' => 'Opportunity Cancelled — {{ opportunity_title }}',
                'body_html' => '<p>Assalamu alaikum {{ volunteer_name }},</p>'
                    . '<p>Please note that the volunteer opportunity <strong>{{ opportunity_title }}</strong> has been cancelled and your shift is no longer scheduled.</p>'
                    . '<p>Thank you for offering your help!</p>',
            ];
        }

        if ($key === NotificationType::VmsAdminSignupReceived->value) {
            return [
                'subject' => 'Admin Notice: New Volunteer Signup — {{ opportunity_title }}',
                'body_html' => '<p>A new volunteer signup has been received for <strong>{{ opportunity_title }}</strong>.</p>'
                    . '<p>Volunteer: {{ volunteer_name }} ({{ volunteer_email }})<br>'
                    . 'Team: {{ team_name }}<br>'
                    . 'Shift: {{ shift_name }}</p>',
            ];
        }

        if ($key === NotificationType::VmsAdminSignupCancelled->value) {
            return [
                'subject' => 'Admin Notice: Volunteer Cancelled — {{ opportunity_title }}',
                'body_html' => '<p>A volunteer has cancelled their signup for <strong>{{ opportunity_title }}</strong>.</p>'
                    . '<p>Volunteer: {{ volunteer_name }} ({{ volunteer_email }})<br>'
                    . 'Shift: {{ shift_name }}</p>',
            ];
        }

        $name = NotificationType::tryFrom($key)?->label() ?? 'MSA Event Update';

        return [
            'subject' => $name . ' — {{ event_name }}',
            'body_html' => '<p>Assalamu alaikum {{ attendee_name }},</p>'
                . '<p>This is a message about <strong>{{ event_name }}</strong>.</p>'
                . '<p>Date: {{ event_date }} at {{ event_time }}<br>Location: {{ event_location }}</p>'
                . '<p><a href="{{ event_details_link }}">View event details</a></p>',
        ];
    }
}
