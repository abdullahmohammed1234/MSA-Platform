<?php

namespace App\Ems\Services\Operations;

use App\Ems\Enums\PaymentStatus;
use App\Ems\Enums\RegistrationStatus;
use App\Ems\Enums\WaitlistStatus;
use App\Ems\Models\CheckIn;
use App\Ems\Models\Event;
use App\Ems\Models\Payment;
use App\Ems\Models\Registration;
use App\Ems\Models\WaitlistEntry;
use Illuminate\Support\Collection;

class EventOperationsService
{
    /**
     * @return array<string, mixed>
     */
    public function summary(Event $event, bool $includePaymentSummary = true): array
    {
        $registered = (int) Registration::query()
            ->where('event_id', $event->id)
            ->whereIn('status', [
                RegistrationStatus::Pending->value,
                RegistrationStatus::AwaitingPayment->value,
                RegistrationStatus::Confirmed->value,
            ])
            ->sum('quantity');

        $confirmed = (int) Registration::query()
            ->where('event_id', $event->id)
            ->where('status', RegistrationStatus::Confirmed->value)
            ->sum('quantity');

        $checkedIn = (int) CheckIn::query()
            ->where('event_id', $event->id)
            ->count();

        $walkIns = (int) Registration::query()
            ->where('event_id', $event->id)
            ->where('metadata->source', 'walk_in')
            ->count();

        $waitlist = (int) WaitlistEntry::query()
            ->where('event_id', $event->id)
            ->where('status', WaitlistStatus::Waiting->value)
            ->count();

        $capacity = $event->capacity;
        $remaining = $capacity !== null ? max(0, (int) $capacity - $registered) : null;
        $attendancePct = $confirmed > 0
            ? round(($checkedIn / $confirmed) * 100, 1)
            : 0.0;

        $statusSummary = Registration::query()
            ->where('event_id', $event->id)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $payload = [
            'event_uuid' => $event->uuid,
            'event_name' => $event->name,
            'event_status' => $event->status->value,
            'registered_count' => $registered,
            'confirmed_count' => $confirmed,
            'checked_in_count' => $checkedIn,
            'remaining_count' => $remaining,
            'capacity' => $capacity,
            'waitlist_count' => $waitlist,
            'walk_in_count' => $walkIns,
            'attendance_percentage' => $attendancePct,
            'registration_status_summary' => $statusSummary,
            'recent_check_ins' => $this->recentCheckIns($event, 15),
        ];

        if ($includePaymentSummary) {
            $payload['payment_summary'] = $this->paymentSummary($event);
        }

        return $payload;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function recentCheckIns(Event $event, int $limit = 15): Collection
    {
        return CheckIn::query()
            ->where('event_id', $event->id)
            ->with(['registration', 'ticket', 'checkedInBy'])
            ->orderByDesc('checked_in_at')
            ->limit($limit)
            ->get()
            ->map(fn (CheckIn $c) => [
                'uuid' => $c->uuid,
                'attendee_name' => $c->registration?->attendee_name ?? $c->ticket?->holder_name,
                'ticket_code' => $c->ticket?->code,
                'method' => $c->method->value,
                'method_label' => $c->method->label(),
                'checked_in_at' => $c->checked_in_at?->toIso8601String(),
                'staff_name' => $c->checkedInBy?->name,
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentSummary(Event $event): array
    {
        $paid = (float) Payment::query()
            ->whereHas('registration', fn ($q) => $q->where('event_id', $event->id))
            ->where('status', PaymentStatus::Paid->value)
            ->sum('amount');

        $pending = (float) Payment::query()
            ->whereHas('registration', fn ($q) => $q->where('event_id', $event->id))
            ->whereIn('status', [
                PaymentStatus::Pending->value,
                PaymentStatus::Processing->value,
                PaymentStatus::Authorized->value,
            ])
            ->sum('amount');

        $failed = (int) Payment::query()
            ->whereHas('registration', fn ($q) => $q->where('event_id', $event->id))
            ->where('status', PaymentStatus::Failed->value)
            ->count();

        $refunded = (float) Payment::query()
            ->whereHas('registration', fn ($q) => $q->where('event_id', $event->id))
            ->whereIn('status', [
                PaymentStatus::Refunded->value,
                PaymentStatus::PartiallyRefunded->value,
            ])
            ->sum('amount');

        return [
            'paid_amount' => $paid,
            'pending_amount' => $pending,
            'refunded_amount' => $refunded,
            'failed_count' => $failed,
            'currency' => (string) config('ems.defaults.currency', 'CAD'),
        ];
    }
}
