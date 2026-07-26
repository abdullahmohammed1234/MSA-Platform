<?php

namespace App\Ems\Services\Notifications;

use App\Ems\Enums\NotificationStatus;
use App\Ems\Enums\NotificationType;
use App\Ems\Models\Event;
use App\Ems\Models\EventNotification;
use App\Ems\Models\EventReminder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class NotificationHistoryService
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateForEvent(Event $event, array $filters = []): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 20), 100));

        $query = EventNotification::query()
            ->where('event_id', $event->id)
            ->with(['registration:id,uuid,reference,attendee_name,attendee_email'])
            ->orderByDesc('id');

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $search = '%' . trim((string) $filters['search']) . '%';
            $query->where(function ($q) use ($search): void {
                $q->where('recipient_email', 'like', $search)
                    ->orWhere('subject', 'like', $search)
                    ->orWhere('type', 'like', $search);
            });
        }

        return $query->paginate($perPage);
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(Event $event): array
    {
        $base = EventNotification::query()->where('event_id', $event->id);

        $pendingReminders = EventReminder::query()
            ->where('event_id', $event->id)
            ->where('enabled', true)
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '>', now())
            ->count();

        return [
            'total' => (clone $base)->count(),
            'queued' => (clone $base)->whereIn('status', [
                NotificationStatus::Pending->value,
                NotificationStatus::Scheduled->value,
            ])->count(),
            'sent' => (clone $base)->where('status', NotificationStatus::Sent->value)->count(),
            'failed' => (clone $base)->where('status', NotificationStatus::Failed->value)->count(),
            'cancelled' => (clone $base)->where('status', NotificationStatus::Cancelled->value)->count(),
            'pending_reminders' => $pendingReminders,
            'by_type' => (clone $base)
                ->selectRaw('type, count(*) as count')
                ->groupBy('type')
                ->pluck('count', 'type')
                ->all(),
            'types' => array_map(fn (NotificationType $t) => [
                'value' => $t->value,
                'label' => $t->label(),
                'category' => $t->category(),
                'resendable' => in_array($t, NotificationType::resendable(), true),
            ], NotificationType::cases()),
        ];
    }
}
