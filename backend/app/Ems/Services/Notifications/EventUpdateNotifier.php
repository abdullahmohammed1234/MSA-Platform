<?php

namespace App\Ems\Services\Notifications;

use App\Ems\Contracts\EventNotificationDispatcher;
use App\Ems\Enums\NotificationAudience;
use App\Ems\Enums\NotificationType;
use App\Ems\Models\Event;
use Illuminate\Support\Facades\Log;

/**
 * Detects meaningful event field changes and optionally notifies attendees.
 */
class EventUpdateNotifier
{
    /**
     * Fields that warrant an update notification when changed.
     *
     * @var array<int, string>
     */
    public const NOTABLE_FIELDS = [
        'name',
        'description',
        'short_description',
        'start_at',
        'end_at',
        'timezone',
        'location',
        'capacity',
        'registration_deadline_at',
        'waitlist_enabled',
        'max_tickets_per_order',
        'max_registrations_per_attendee',
    ];

    public function __construct(
        private readonly EventNotificationDispatcher $dispatcher,
        private readonly ReminderService $reminders,
    ) {
    }

    /**
     * @param  array<int, string>  $changed
     * @return array{notified: int, summary: string, audience: string}
     */
    public function handle(
        Event $event,
        array $changed,
        NotificationAudience $audience = NotificationAudience::Registered
    ): array {
        $notable = array_values(array_intersect($changed, self::NOTABLE_FIELDS));

        if (in_array('start_at', $notable, true) || in_array('timezone', $notable, true)) {
            $this->reminders->recalculateForEvent($event);
        }

        if ($notable === [] || $audience === NotificationAudience::None) {
            return [
                'notified' => 0,
                'summary' => '',
                'audience' => $audience->value,
            ];
        }

        $summary = $this->summarize($notable);

        $count = $this->dispatcher->broadcastToEvent($event, NotificationType::EventUpdated->value, [
            'audience' => $audience->value,
            'change_summary' => $summary,
            'idempotency_suffix' => 'update:' . md5(implode(',', $notable) . now()->format('YmdHi')),
        ]);

        Log::channel((string) config('ems.logging.channel', 'ems'))
            ->info('ems.notifications.event_updated', [
                'event_uuid' => $event->uuid,
                'changed' => $notable,
                'notified' => $count,
                'audience' => $audience->value,
            ]);

        return [
            'notified' => $count,
            'summary' => $summary,
            'audience' => $audience->value,
        ];
    }

    /**
     * @param  array<int, string>  $fields
     */
    public function summarize(array $fields): string
    {
        $labels = [
            'name' => 'event name',
            'description' => 'description',
            'short_description' => 'summary',
            'start_at' => 'date/time',
            'end_at' => 'end time',
            'timezone' => 'time zone',
            'location' => 'location',
            'capacity' => 'capacity',
            'registration_deadline_at' => 'registration deadline',
            'waitlist_enabled' => 'waitlist settings',
            'max_tickets_per_order' => 'ticket limits',
            'max_registrations_per_attendee' => 'registration limits',
        ];

        $parts = array_map(fn (string $f) => $labels[$f] ?? $f, $fields);

        return 'Updated: ' . implode(', ', $parts) . '.';
    }
}
