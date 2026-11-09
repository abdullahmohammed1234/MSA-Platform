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
use Illuminate\Support\Facades\DB;

class EventOperationsService
{
    /**
     * @return array<string, mixed>
     */
    public function summary(Event $event, bool $includePaymentSummary = true): array
    {
        $registrationStats = Registration::query()
            ->where('event_id', $event->id)
            ->selectRaw('status, COUNT(*) as total, SUM(quantity) as qty')
            ->groupBy('status')
            ->get();

        $statusSummary = [];
        $registered = 0;
        $confirmed = 0;

        foreach ($registrationStats as $stat) {
            $status = $stat->status instanceof RegistrationStatus ? $stat->status->value : (string) $stat->status;
            $count = (int) $stat->total;
            $qty = (int) ($stat->qty ?? 0);

            $statusSummary[$status] = $count;

            if (in_array($status, [
                RegistrationStatus::Pending->value,
                RegistrationStatus::AwaitingPayment->value,
                RegistrationStatus::Confirmed->value,
            ], true)) {
                $registered += $qty;
            }

            if ($status === RegistrationStatus::Confirmed->value) {
                $confirmed += $qty;
            }
        }

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
        $paymentRows = DB::table('ems_payments')
            ->join('ems_registrations', 'ems_registrations.id', '=', 'ems_payments.registration_id')
            ->where('ems_registrations.event_id', $event->id)
            ->whereNull('ems_registrations.deleted_at')
            ->selectRaw('ems_payments.status, SUM(ems_payments.amount) as total_amount, COUNT(*) as total_count')
            ->groupBy('ems_payments.status')
            ->get();

        $paid = 0.0;
        $pending = 0.0;
        $failed = 0;
        $refunded = 0.0;

        foreach ($paymentRows as $row) {
            $status = (string) $row->status;
            $amt = (float) $row->total_amount;
            $cnt = (int) $row->total_count;

            if ($status === PaymentStatus::Paid->value) {
                $paid += $amt;
            } elseif (in_array($status, [
                PaymentStatus::Pending->value,
                PaymentStatus::Processing->value,
                PaymentStatus::Authorized->value,
            ], true)) {
                $pending += $amt;
            } elseif ($status === PaymentStatus::Failed->value) {
                $failed += $cnt;
            } elseif (in_array($status, [
                PaymentStatus::Refunded->value,
                PaymentStatus::PartiallyRefunded->value,
            ], true)) {
                $refunded += $amt;
            }
        }

        return [
            'paid_amount' => $paid,
            'pending_amount' => $pending,
            'refunded_amount' => $refunded,
            'failed_count' => $failed,
            'currency' => (string) config('ems.defaults.currency', 'CAD'),
        ];
    }
}
