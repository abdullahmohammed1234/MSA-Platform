<?php

namespace App\Ems\Services\Operations;

use App\Ems\Enums\CheckInStatus;
use App\Ems\Enums\PaymentStatus;
use App\Ems\Enums\RegistrationStatus;
use App\Ems\Models\Event;
use App\Ems\Models\Registration;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class AttendeeService
{
    private const SORTABLE = [
        'name' => 'attendee_name',
        'registration_date' => 'registered_at',
        'ticket_type' => 'ticket_type_id',
        'payment_status' => 'status',
        'check_in_time' => 'check_in_time',
    ];

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Registration>
     */
    public function paginate(Event $event, array $filters = []): LengthAwarePaginator
    {
        $sortKey = (string) ($filters['sort_by'] ?? 'registration_date');
        $sortColumn = self::SORTABLE[$sortKey] ?? 'registered_at';
        $direction = strtolower((string) ($filters['sort_direction'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
        $perPage = $this->resolvePerPage($filters['per_page'] ?? null);

        $query = Registration::query()
            ->where('event_id', $event->id)
            ->with([
                'ticketType',
                'tickets.checkIn.checkedInBy',
                'payments',
                'order',
                'checkIns.checkedInBy',
            ])
            ->withMax('checkIns as check_in_time', 'checked_in_at');

        $this->applySearch($query, $filters['search'] ?? null);
        $this->applyFilters($query, $filters, $event);

        if ($sortColumn === 'check_in_time') {
            $query->orderBy('check_in_time', $direction);
        } else {
            $query->orderBy($sortColumn, $direction);
        }

        $paginator = $query->orderBy('id', 'desc')->paginate($perPage)->withQueryString();
        $paginator->getCollection()->each(
            fn (Registration $registration) => $registration->setRelation('event', $event)
        );

        return $paginator;
    }

    /**
     * @param  Builder<Registration>  $query
     */
    private function applySearch(Builder $query, mixed $search): void
    {
        $term = trim((string) ($search ?? ''));
        if ($term === '') {
            return;
        }

        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $term) . '%';

        $query->where(function (Builder $q) use ($like, $term) {
            $q->where('attendee_name', 'like', $like)
                ->orWhere('attendee_email', 'like', $like)
                ->orWhere('attendee_phone', 'like', $like)
                ->orWhere('reference', 'like', $like)
                ->orWhere('uuid', $term)
                ->orWhereHas('tickets', function (Builder $t) use ($like, $term) {
                    $t->where('code', 'like', $like)
                        ->orWhere('qr_payload', 'like', $like)
                        ->orWhere('uuid', $term);
                });
        });
    }

    /**
     * @param  Builder<Registration>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters, Event $event): void
    {
        if (! empty($filters['ticket_type_id'])) {
            $query->whereHas('ticketType', fn (Builder $q) => $q->where('uuid', $filters['ticket_type_id']));
        }

        if (! empty($filters['registration_status'])) {
            $status = $this->normalizeRegistrationStatus((string) $filters['registration_status']);
            if ($status !== null) {
                $query->where('status', $status);
            }
        }

        if (! empty($filters['payment_status'])) {
            $paymentStatus = (string) $filters['payment_status'];
            if ($paymentStatus === PaymentStatus::Paid->value) {
                $query->where(function (Builder $q) {
                    $q->where('type', 'free')
                        ->orWhereHas('payments', fn (Builder $p) => $p->where('status', PaymentStatus::Paid->value))
                        ->orWhere('status', RegistrationStatus::Confirmed->value);
                });
            } elseif ($paymentStatus === PaymentStatus::Pending->value) {
                $query->where('status', RegistrationStatus::AwaitingPayment->value);
            } else {
                $query->whereHas('payments', fn (Builder $p) => $p->where('status', $paymentStatus));
            }
        }

        if (! empty($filters['check_in_status'])) {
            match ((string) $filters['check_in_status']) {
                CheckInStatus::CheckedIn->value => $query->whereHas('checkIns'),
                CheckInStatus::NotCheckedIn->value => $query->whereDoesntHave('checkIns'),
                CheckInStatus::NoShow->value => $this->constrainNoShows($query, $event),
                // Checked-out is foundation-only; treat as empty set for now.
                CheckInStatus::CheckedOut->value => $query->whereRaw('1 = 0'),
                default => null,
            };
        }

        if (array_key_exists('is_member', $filters) && $filters['is_member'] !== null && $filters['is_member'] !== '') {
            $isMember = filter_var($filters['is_member'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($isMember === true) {
                $query->where('metadata->is_member', true);
            } elseif ($isMember === false) {
                $query->where(function (Builder $q) {
                    $q->where('metadata->is_member', false)
                        ->orWhereNull('metadata->is_member');
                });
            }
        }

        if (! empty($filters['source'])) {
            $source = (string) $filters['source'];
            if ($source === 'imported') {
                $query->where('metadata->source', 'imported');
            } elseif ($source === 'walk_in') {
                $query->where('metadata->source', 'walk_in');
            } elseif ($source === 'square_online_store') {
                $query->where(function (Builder $q) {
                    $q->where('metadata->source', 'square_online_store')
                        ->orWhereHas('order', fn (Builder $o) => $o->where('source_channel', 'square_online_store'))
                        ->orWhereHas('payments', fn (Builder $p) => $p->where('source_channel', 'square_online_store'));
                });
            } elseif ($source === 'ems') {
                $query->where(function (Builder $q) {
                    $q->whereNull('metadata->source')
                        ->orWhere('metadata->source', 'ems');
                });
            }
        }
    }

    /**
     * @param  Builder<Registration>  $query
     */
    private function constrainNoShows(Builder $query, Event $event): void
    {
        if (! $event->hasEnded()) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereDoesntHave('checkIns')
            ->where('status', RegistrationStatus::Confirmed->value);
    }

    private function normalizeRegistrationStatus(string $value): ?string
    {
        $map = [
            'pending_payment' => RegistrationStatus::AwaitingPayment->value,
            'pending payment' => RegistrationStatus::AwaitingPayment->value,
            'awaiting_payment' => RegistrationStatus::AwaitingPayment->value,
            'registered' => RegistrationStatus::Confirmed->value,
            'confirmed' => RegistrationStatus::Confirmed->value,
            'waitlisted' => RegistrationStatus::Waitlisted->value,
            'cancelled' => RegistrationStatus::Cancelled->value,
            'canceled' => RegistrationStatus::Cancelled->value,
            'refunded' => RegistrationStatus::Refunded->value,
            'pending' => RegistrationStatus::Pending->value,
        ];

        $key = strtolower(trim($value));

        return $map[$key] ?? (in_array($key, RegistrationStatus::values(), true) ? $key : null);
    }

    private function resolvePerPage(mixed $requested): int
    {
        $default = (int) config('ems.defaults.per_page', 15);
        $max = (int) config('ems.defaults.max_per_page', 100);
        $perPage = is_numeric($requested) ? (int) $requested : $default;

        return max(1, min($perPage, $max));
    }
}
