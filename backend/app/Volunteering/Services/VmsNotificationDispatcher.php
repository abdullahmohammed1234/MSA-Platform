<?php

namespace App\Volunteering\Services;

use App\Ems\Enums\NotificationChannel;
use App\Ems\Enums\NotificationStatus;
use App\Ems\Enums\NotificationType;
use App\Ems\Jobs\SendEventNotificationJob;
use App\Ems\Models\EventNotification;
use App\Ems\Services\Notifications\TemplateRenderer;
use App\Volunteering\Models\Signup;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VmsNotificationDispatcher
{
    public function __construct(
        private readonly TemplateRenderer $renderer
    ) {
    }

    public function notifySignupConfirmed(Signup $signup, array $payload = []): ?EventNotification
    {
        return $this->dispatchNotification(
            signup: $signup,
            type: NotificationType::VmsSignupConfirmed,
            idempotencyKey: "vms_signup_confirmed:{$signup->id}",
            payload: $payload
        );
    }

    public function notifyWaitlistJoined(Signup $signup, array $payload = []): ?EventNotification
    {
        return $this->dispatchNotification(
            signup: $signup,
            type: NotificationType::VmsWaitlistJoined,
            idempotencyKey: "vms_waitlist_joined:{$signup->id}",
            payload: $payload
        );
    }

    public function notifyWaitlistPromoted(Signup $signup, array $payload = []): ?EventNotification
    {
        return $this->dispatchNotification(
            signup: $signup,
            type: NotificationType::VmsWaitlistPromoted,
            idempotencyKey: "vms_waitlist_promoted:{$signup->id}",
            payload: $payload
        );
    }

    public function notifySignupCancelled(Signup $signup, array $payload = []): ?EventNotification
    {
        return $this->dispatchNotification(
            signup: $signup,
            type: NotificationType::VmsSignupCancelled,
            idempotencyKey: "vms_signup_cancelled:{$signup->id}",
            payload: $payload
        );
    }

    public function notifyShiftUpdated(Signup $signup, array $changes = [], array $payload = []): ?EventNotification
    {
        $changeSummaryLines = [];
        foreach ($changes as $field => $val) {
            $changeSummaryLines[] = ucfirst($field) . ': ' . (is_array($val) ? json_encode($val) : (string) $val);
        }
        $changeSummary = implode("\n", $changeSummaryLines);
        $hash = md5($changeSummary ?: (string) now()->timestamp);

        return $this->dispatchNotification(
            signup: $signup,
            type: NotificationType::VmsShiftUpdated,
            idempotencyKey: "vms_shift_updated:{$signup->id}:{$hash}",
            payload: array_merge($payload, [
                'change_summary' => $changeSummary,
            ])
        );
    }

    public function notifyOpportunityCancelled(Signup $signup, string $reason = '', array $payload = []): ?EventNotification
    {
        return $this->dispatchNotification(
            signup: $signup,
            type: NotificationType::VmsOpportunityCancelled,
            idempotencyKey: "vms_opportunity_cancelled:{$signup->id}",
            payload: array_merge($payload, [
                'cancellation_reason' => $reason,
            ])
        );
    }

    public function notifyShiftReminder(Signup $signup, int $hoursBefore, array $payload = []): ?EventNotification
    {
        return $this->dispatchNotification(
            signup: $signup,
            type: NotificationType::VmsShiftReminder,
            idempotencyKey: "vms_shift_reminder:{$signup->id}:{$hoursBefore}h",
            payload: array_merge($payload, [
                'reminder_hours' => $hoursBefore,
            ])
        );
    }

    public function notifyAdminSignupReceived(Signup $signup, array $payload = []): ?EventNotification
    {
        $adminRecipients = $this->resolveAdminRecipients($signup);
        if (empty($adminRecipients)) {
            return null;
        }

        $firstAdmin = array_shift($adminRecipients);

        return $this->dispatchNotification(
            signup: $signup,
            type: NotificationType::VmsAdminSignupReceived,
            idempotencyKey: "vms_admin_signup_received:{$signup->id}",
            payload: array_merge($payload, [
                'recipient_email' => $firstAdmin,
            ]),
            recipientEmail: $firstAdmin
        );
    }

    public function notifyAdminSignupCancelled(Signup $signup, array $payload = []): ?EventNotification
    {
        $adminRecipients = $this->resolveAdminRecipients($signup);
        if (empty($adminRecipients)) {
            return null;
        }

        $firstAdmin = array_shift($adminRecipients);

        return $this->dispatchNotification(
            signup: $signup,
            type: NotificationType::VmsAdminSignupCancelled,
            idempotencyKey: "vms_admin_signup_cancelled:{$signup->id}",
            payload: array_merge($payload, [
                'recipient_email' => $firstAdmin,
            ]),
            recipientEmail: $firstAdmin
        );
    }

    /**
     * Dispatch an event notification using the existing platform infrastructure.
     */
    public function dispatchNotification(
        Signup $signup,
        NotificationType $type,
        string $idempotencyKey,
        array $payload = [],
        ?string $recipientEmail = null,
        ?Carbon $scheduledAt = null
    ): EventNotification {
        $signup->loadMissing(['opportunity.event', 'team', 'shift', 'user']);

        $force = (bool) ($payload['force'] ?? false);

        if (! $force) {
            $existing = EventNotification::query()
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing !== null) {
                if ($existing->status === NotificationStatus::Failed) {
                    $existing->status = NotificationStatus::Pending;
                    $existing->queue_status = 'pending';
                    $existing->error = null;
                    $existing->failed_at = null;
                    $existing->scheduled_at = $scheduledAt ?? now();
                    $existing->save();

                    SendEventNotificationJob::dispatch($existing->id);
                }

                return $existing;
            }
        } else {
            $idempotencyKey .= ':resend:' . now()->timestamp;
        }

        $recipient = $recipientEmail ?? $payload['recipient_email'] ?? $signup->email;
        if (blank($recipient)) {
            Log::warning('vms.notifications.missing_recipient', [
                'signup_id' => $signup->id,
                'type' => $type->value,
            ]);
        }

        $mergedPayload = array_merge([
            'signup' => $signup,
            'opportunity' => $signup->opportunity,
            'shift' => $signup->shift,
            'team' => $signup->team,
            'volunteer_name' => $signup->name,
            'volunteer_email' => $signup->email,
        ], $payload);

        $templateKey = (string) ($payload['template_key'] ?? $type->value);
        $rendered = $this->renderer->render($templateKey, $mergedPayload);

        $notification = new EventNotification();
        $notification->event_id = $signup->opportunity?->event_id;
        $notification->volunteering_signup_id = $signup->id;
        $notification->user_id = $signup->user_id;
        $notification->recipient_email = $recipient;
        $notification->channel = NotificationChannel::Mail;
        $notification->type = $type->value;
        $notification->template_key = $rendered['template_key'];
        $notification->idempotency_key = $idempotencyKey;
        $notification->subject = $rendered['subject'];
        $notification->body = $rendered['body_text'];
        $notification->status = NotificationStatus::Pending;
        $notification->scheduled_at = $scheduledAt ?? now();
        $notification->payload = array_merge($payload, [
            'body_html' => $rendered['body_html'],
            'body_text' => $rendered['body_text'],
            'volunteering_signup_id' => $signup->id,
            'opportunity_id' => $signup->opportunity_id,
            'shift_id' => $signup->shift_id,
        ]);
        $notification->save();

        Log::info('vms.notifications.created', [
            'notification_uuid' => $notification->uuid,
            'type' => $notification->type,
            'signup_id' => $signup->id,
            'recipient' => $recipient,
        ]);

        $notification->markQueued();
        SendEventNotificationJob::dispatch($notification->id);

        return $notification;
    }

    /**
     * Resolve administrator notification email addresses for a signup.
     *
     * @return array<int, string>
     */
    private function resolveAdminRecipients(Signup $signup): array
    {
        $recipients = config('vms.notifications.admin_recipients', []);
        if (is_string($recipients)) {
            $recipients = array_filter(explode(',', $recipients));
        }

        if (empty($recipients)) {
            // Check opportunity creator or default mail from address
            if ($signup->opportunity?->creator?->email) {
                $recipients[] = $signup->opportunity->creator->email;
            } else {
                $from = config('ems.notifications.from_address', config('mail.from.address'));
                if ($from) {
                    $recipients[] = $from;
                }
            }
        }

        return array_values(array_unique(array_filter($recipients)));
    }
}
