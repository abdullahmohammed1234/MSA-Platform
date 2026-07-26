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

        $ticketCodes = $tickets->pluck('code')->filter()->values()->all();
        $firstTicket = $tickets->first();
        $qrUrl = $firstTicket instanceof Ticket
            ? $this->ticketQrUrl($firstTicket)
            : '';
        $ticketUrl = $firstTicket instanceof Ticket
            ? $this->ticketUrl($firstTicket)
            : '';

        $start = $event?->start_at?->timezone($event->timezone ?? config('ems.defaults.timezone'));

        return [
            'attendee_name' => (string) ($registration?->attendee_name ?? $extra['attendee_name'] ?? ''),
            'attendee_email' => (string) ($registration?->attendee_email ?? $extra['attendee_email'] ?? ''),
            'event_name' => (string) ($event?->name ?? $extra['event_name'] ?? ''),
            'event_date' => $start?->format('l, F j, Y') ?? '',
            'event_time' => $start?->format('g:i A T') ?? '',
            'event_location' => (string) ($event?->location ?? ''),
            'event_timezone' => (string) ($event?->timezone ?? ''),
            'ticket_type' => (string) ($registration?->ticketType?->name ?? $extra['ticket_type'] ?? ''),
            'registration_number' => (string) ($registration?->reference ?? ''),
            'ticket_number' => (string) ($firstTicket?->code ?? ''),
            'ticket_codes' => implode(', ', $ticketCodes),
            'qr_code' => $qrUrl,
            'qr_code_url' => $qrUrl,
            'ticket_download_link' => $ticketUrl,
            'event_details_link' => $event ? $this->eventPublicUrl($event) : '',
            'feedback_link' => $event ? $this->feedbackUrl($event) : '',
            'order_number' => (string) ($order?->reference ?? ''),
            'payment_status' => (string) ($payment?->status?->value ?? ''),
            'amount_paid' => $payment ? number_format((float) $payment->amount, 2) : '',
            'currency' => (string) ($payment?->currency ?? $order?->currency ?? config('ems.defaults.currency', 'CAD')),
            'payment_reference' => (string) ($payment?->uuid ?? ''),
            'square_transaction_reference' => (string) ($payment?->provider_transaction_id ?? $payment?->provider_payment_id ?? ''),
            'refund_amount' => isset($extra['refund_amount']) ? number_format((float) $extra['refund_amount'], 2) : '',
            'change_summary' => (string) ($extra['change_summary'] ?? ''),
            'organizer_name' => (string) ($event?->organizer_name ?? ($event?->organizer?->name ?? config('ems.notifications.from_name', 'SFU MSA Events'))),
            'cancellation_reason' => (string) ($event?->cancellation_reason ?? $extra['cancellation_reason'] ?? ''),
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
