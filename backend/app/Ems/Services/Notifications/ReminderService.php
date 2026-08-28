<?php

namespace App\Ems\Services\Notifications;

use App\Ems\Contracts\EventNotificationDispatcher;
use App\Ems\Enums\NotificationType;
use App\Ems\Enums\RegistrationStatus;
use App\Ems\Enums\ReminderAudience;
use App\Ems\Enums\ReminderOffsetUnit;
use App\Ems\Models\Event;
use App\Ems\Models\EventReminder;
use App\Ems\Models\Registration;
use App\Ems\Models\ReminderDispatch;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReminderService
{
    public function __construct(
        private readonly EventNotificationDispatcher $dispatcher,
    ) {
    }

    /**
     * @return Collection<int, EventReminder>
     */
    public function listForEvent(Event $event): Collection
    {
        return EventReminder::query()
            ->where('event_id', $event->id)
            ->orderBy('offset_value')
            ->get();
    }

    /**
     * @param  array{
     *     label?: string|null,
     *     offset_value: int,
     *     offset_unit: string,
     *     enabled?: bool,
     *     template_key?: string,
     *     audience?: string
     * }  $data
     */
    public function create(Event $event, array $data): EventReminder
    {
        $reminder = new EventReminder();
        $reminder->event_id = $event->id;
        $reminder->label = $data['label'] ?? null;
        $reminder->offset_value = (int) $data['offset_value'];
        $reminder->offset_unit = ReminderOffsetUnit::from($data['offset_unit']);
        $reminder->enabled = (bool) ($data['enabled'] ?? true);
        $reminder->template_key = $data['template_key'] ?? NotificationType::EventReminder->value;
        $reminder->audience = ReminderAudience::from($data['audience'] ?? ReminderAudience::Confirmed->value);
        $reminder->next_run_at = $reminder->computeNextRunAt($event->start_at);
        $reminder->save();

        Log::channel((string) config('ems.logging.channel', 'ems'))
            ->info('ems.reminders.created', [
                'event_uuid' => $event->uuid,
                'reminder_uuid' => $reminder->uuid,
            ]);

        return $reminder->fresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(EventReminder $reminder, array $data): EventReminder
    {
        if (array_key_exists('label', $data)) {
            $reminder->label = $data['label'];
        }
        if (isset($data['offset_value'])) {
            $reminder->offset_value = (int) $data['offset_value'];
        }
        if (isset($data['offset_unit'])) {
            $reminder->offset_unit = ReminderOffsetUnit::from($data['offset_unit']);
        }
        if (array_key_exists('enabled', $data)) {
            $reminder->enabled = (bool) $data['enabled'];
        }
        if (isset($data['template_key'])) {
            $reminder->template_key = $data['template_key'];
        }
        if (isset($data['audience'])) {
            $reminder->audience = ReminderAudience::from($data['audience']);
        }

        $reminder->loadMissing('event');
        $reminder->next_run_at = $reminder->computeNextRunAt($reminder->event?->start_at);
        $reminder->save();

        Log::channel((string) config('ems.logging.channel', 'ems'))
            ->info('ems.reminders.updated', [
                'reminder_uuid' => $reminder->uuid,
            ]);

        return $reminder->fresh();
    }

    public function delete(EventReminder $reminder): void
    {
        $uuid = $reminder->uuid;
        $reminder->delete();

        Log::channel((string) config('ems.logging.channel', 'ems'))
            ->info('ems.reminders.deleted', ['reminder_uuid' => $uuid]);
    }

    public function recalculateForEvent(Event $event): void
    {
        foreach ($this->listForEvent($event) as $reminder) {
            $reminder->next_run_at = $reminder->computeNextRunAt($event->start_at);
            $reminder->save();
        }
    }

    /**
     * Seed sensible defaults when an event first opens registration.
     */
    public function seedDefaults(Event $event): void
    {
        if ($this->listForEvent($event)->isNotEmpty()) {
            return;
        }

        foreach ([
            ['offset_value' => 7, 'offset_unit' => ReminderOffsetUnit::Days->value, 'label' => '7 Days Before'],
            ['offset_value' => 3, 'offset_unit' => ReminderOffsetUnit::Days->value, 'label' => '3 Days Before'],
            ['offset_value' => 1, 'offset_unit' => ReminderOffsetUnit::Days->value, 'label' => '1 Day Before'],
            ['offset_value' => 6, 'offset_unit' => ReminderOffsetUnit::Hours->value, 'label' => '6 Hours Before'],
            ['offset_value' => 1, 'offset_unit' => ReminderOffsetUnit::Hours->value, 'label' => '1 Hour Before'],
        ] as $row) {
            $this->create($event, array_merge($row, ['enabled' => false]));
        }
    }

    /**
     * Execute due reminders (idempotent per registration).
     */
    public function processDue(int $limit = 50): int
    {
        $processed = 0;

        EventReminder::query()
            ->due()
            ->with('event')
            ->orderBy('next_run_at')
            ->limit($limit)
            ->get()
            ->each(function (EventReminder $reminder) use (&$processed): void {
                $processed += $this->dispatchReminder($reminder);
            });

        return $processed;
    }

    public function dispatchReminder(EventReminder $reminder): int
    {
        $event = $reminder->event;

        if ($event === null || $event->start_at === null || $event->start_at->isPast()) {
            DB::transaction(function () use ($reminder): void {
                $locked = EventReminder::query()->whereKey($reminder->id)->lockForUpdate()->first();
                if ($locked) {
                    $locked->enabled = false;
                    $locked->next_run_at = null;
                    $locked->last_run_at = now();
                    $locked->save();
                }
            });

            return 0;
        }

        $shouldRun = DB::transaction(function () use ($reminder): bool {
            $locked = EventReminder::query()->whereKey($reminder->id)->lockForUpdate()->first();
            if ($locked === null || !$locked->enabled) {
                return false;
            }
            $locked->enabled = false;
            $locked->last_run_at = now();
            $locked->next_run_at = null;
            $locked->save();
            return true;
        });

        if (!$shouldRun) {
            return 0;
        }

        $queued = 0;

        $this->audienceQuery($event, $reminder->audience)
            ->orderBy('ems_registrations.id')
            ->chunkById(100, function ($registrations) use ($reminder, $event, &$queued): void {
                foreach ($registrations as $registration) {
                    try {
                        DB::transaction(function () use ($reminder, $event, $registration, &$queued): void {
                            $regLocked = Registration::query()->whereKey($registration->id)->lockForUpdate()->first();
                            if ($regLocked === null) {
                                return;
                            }

                            $exists = ReminderDispatch::query()
                                ->where('reminder_id', $reminder->id)
                                ->where('registration_id', $regLocked->id)
                                ->exists();

                            if ($exists) {
                                return;
                            }

                            $notification = $this->dispatcher->notifyRegistration(
                                $regLocked,
                                NotificationType::EventReminder->value,
                                [
                                    'template_key' => $reminder->template_key,
                                    'idempotency_suffix' => 'reminder:' . $reminder->id,
                                    'reminder_label' => $reminder->displayLabel(),
                                ]
                            );

                            ReminderDispatch::query()->create([
                                'reminder_id' => $reminder->id,
                                'event_id' => $event->id,
                                'registration_id' => $regLocked->id,
                                'notification_id' => $notification->id,
                                'dispatched_at' => now(),
                            ]);

                            $queued++;
                        });
                    } catch (\Throwable $e) {
                        Log::channel((string) config('ems.logging.channel', 'ems'))
                            ->error('ems.reminders.registration_failed', [
                                'reminder_id' => $reminder->id,
                                'registration_id' => $registration->id,
                                'error' => $e->getMessage(),
                            ]);
                    }
                }
            });

        Log::channel((string) config('ems.logging.channel', 'ems'))
            ->info('ems.reminders.executed', [
                'reminder_uuid' => $reminder->uuid,
                'event_uuid' => $event->uuid,
                'queued' => $queued,
            ]);

        return $queued;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Registration>
     */
    private function audienceQuery(Event $event, ReminderAudience $audience)
    {
        $query = Registration::query()
            ->where('event_id', $event->id)
            ->with(['event.organizer', 'ticketType', 'tickets', 'order', 'settledPayment', 'user']);

        return match ($audience) {
            ReminderAudience::All => $query->whereIn('status', [
                RegistrationStatus::Confirmed->value,
                RegistrationStatus::Pending->value,
                RegistrationStatus::AwaitingPayment->value,
                RegistrationStatus::Waitlisted->value,
            ]),
            ReminderAudience::TicketHolders => $query
                ->where('status', RegistrationStatus::Confirmed->value)
                ->whereHas('tickets'),
            ReminderAudience::Confirmed => $query->where('status', RegistrationStatus::Confirmed->value),
        };
    }
}
