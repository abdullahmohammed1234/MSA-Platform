<?php

namespace App\Ems\Services;

use App\Ems\Contracts\TicketIssuer;
use App\Ems\Enums\RegistrationStatus;
use App\Ems\Enums\WaitlistStatus;
use App\Ems\Exceptions\EmsException;
use App\Ems\Models\Event;
use App\Ems\Models\Registration;
use App\Ems\Models\TicketType;
use App\Ems\Models\WaitlistEntry;
use App\Ems\Services\Notifications\EventCommunicationService;
use App\Ems\Services\Ticketing\TicketCodeGenerator;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class WaitlistService
{
    public function __construct(
        private readonly TicketCodeGenerator $codes,
        private readonly EventCommunicationService $communications,
        private readonly TicketIssuer $tickets,
    ) {
    }

    /**
     * @param  array{
     *     first_name: string,
     *     last_name: string,
     *     email: string,
     *     phone?: string|null,
     *     quantity?: int,
     *     ticket_type_id?: string|null
     * }  $data
     */
    public function join(Event $event, array $data, ?User $user = null): WaitlistEntry
    {
        if (! $event->waitlist_enabled) {
            throw new EmsException(
                'The waitlist is not enabled for this event.',
                ['event' => ['Waitlist disabled.']],
                Response::HTTP_CONFLICT
            );
        }

        $email = strtolower(trim($data['email']));
        $quantity = max(1, (int) ($data['quantity'] ?? 1));
        $name = trim(trim($data['first_name']) . ' ' . trim($data['last_name']));

        return DB::transaction(function () use ($event, $data, $user, $email, $quantity, $name): WaitlistEntry {
            $locked = Event::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();

            $existing = WaitlistEntry::query()
                ->where('event_id', $locked->id)
                ->where('attendee_email', $email)
                ->where('status', WaitlistStatus::Waiting->value)
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $ticketTypeId = null;
            if (! empty($data['ticket_type_id'])) {
                $ticketType = $locked->ticketTypes()
                    ->where('uuid', $data['ticket_type_id'])
                    ->first();
                $ticketTypeId = $ticketType?->id;
            }

            $position = (int) WaitlistEntry::query()
                ->where('event_id', $locked->id)
                ->where('status', WaitlistStatus::Waiting->value)
                ->max('position') + 1;

            $registration = new Registration();
            $registration->reference = $this->codes->registrationReference();
            $registration->event_id = $locked->id;
            $registration->user_id = $user?->id;
            $registration->ticket_type_id = $ticketTypeId;
            $registration->attendee_name = $name;
            $registration->attendee_email = $email;
            $registration->attendee_phone = isset($data['phone']) ? trim((string) $data['phone']) : null;
            $registration->status = RegistrationStatus::Waitlisted;
            $registration->quantity = $quantity;
            $registration->waitlist_position = $position;
            $registration->amount_due = 0;
            $registration->currency = (string) config('ems.defaults.currency', 'CAD');
            $registration->registered_at = now();
            $registration->save();

            $entry = new WaitlistEntry();
            $entry->event_id = $locked->id;
            $entry->ticket_type_id = $ticketTypeId;
            $entry->user_id = $user?->id;
            $entry->registration_id = $registration->id;
            $entry->attendee_name = $name;
            $entry->attendee_email = $email;
            $entry->attendee_phone = $registration->attendee_phone;
            $entry->position = $position;
            $entry->quantity = $quantity;
            $entry->status = WaitlistStatus::Waiting;
            $entry->save();

            Log::channel((string) config('ems.logging.channel', 'ems'))
                ->info('ems.waitlist.joined', [
                    'event_uuid' => $locked->uuid,
                    'entry_uuid' => $entry->uuid,
                    'position' => $position,
                ]);

            $this->communications->sendWaitlistJoined($registration->loadMissing(['event', 'ticketType', 'tickets', 'order']));

            return $entry->fresh(['registration', 'ticketType', 'event']);
        });
    }

    public function leave(Event $event, string $entryUuid, ?string $email = null): void
    {
        DB::transaction(function () use ($event, $entryUuid, $email): void {
            // 1. Lock Event first to preserve canonical lock ordering
            Event::query()->withTrashed()->whereKey($event->id)->lockForUpdate()->first();

            // 2. Lock WaitlistEntry
            $entry = WaitlistEntry::query()
                ->where('event_id', $event->id)
                ->where('uuid', $entryUuid)
                ->lockForUpdate()
                ->firstOrFail();

            if ($email !== null && strtolower($entry->attendee_email) !== strtolower($email)) {
                throw new EmsException(
                    'Waitlist entry not found.',
                    [],
                    Response::HTTP_NOT_FOUND
                );
            }

            if ($entry->status !== WaitlistStatus::Waiting) {
                return;
            }

            $entry->status = WaitlistStatus::Left;
            $entry->left_at = now();
            $entry->save();

            // 3. Lock Registration
            if ($entry->registration_id) {
                $registration = Registration::query()
                    ->whereKey($entry->registration_id)
                    ->lockForUpdate()
                    ->first();

                if ($registration && $registration->status !== RegistrationStatus::Cancelled) {
                    $registration->status = RegistrationStatus::Cancelled;
                    $registration->cancelled_at = now();
                    $registration->save();
                    $this->communications->sendWaitlistRemoved(
                        $registration->loadMissing(['event', 'ticketType', 'tickets', 'order'])
                    );
                }
            }

            $this->resequence($event);

            Log::channel((string) config('ems.logging.channel', 'ems'))
                ->info('ems.waitlist.left', [
                    'event_uuid' => $event->uuid,
                    'entry_uuid' => $entry->uuid,
                ]);
        });
    }

    /**
     * Promote the next waiting attendees when capacity frees up.
     */
    public function promoteAvailable(Event $event): int
    {
        $promoted = 0;

        DB::transaction(function () use ($event, &$promoted): void {
            $locked = Event::query()->withTrashed()->whereKey($event->id)->lockForUpdate()->firstOrFail();

            if ($locked->trashed() || ! $locked->waitlist_enabled) {
                return;
            }

            $entries = WaitlistEntry::query()
                ->where('event_id', $locked->id)
                ->waiting()
                ->lockForUpdate()
                ->get();

            foreach ($entries as $entry) {
                $ticketType = null;
                if ($entry->ticket_type_id) {
                    $ticketType = TicketType::query()
                        ->whereKey($entry->ticket_type_id)
                        ->lockForUpdate()
                        ->first();
                }

                $registration = null;
                if ($entry->registration_id) {
                    $registration = Registration::query()
                        ->whereKey($entry->registration_id)
                        ->lockForUpdate()
                        ->first();
                }

                if ($registration === null || $registration->status !== RegistrationStatus::Waitlisted) {
                    // Stale entry: registration was cancelled, soft-deleted, or changed status.
                    $entry->status = WaitlistStatus::Left;
                    $entry->left_at = now();
                    $entry->save();
                    continue;
                }

                if (! $locked->hasAvailableCapacity($entry->quantity)) {
                    break;
                }

                if ($ticketType !== null && ! $ticketType->hasAvailableQuantity($entry->quantity)) {
                    continue;
                }

                $entry->status = WaitlistStatus::Promoted;
                $entry->promoted_at = now();
                $entry->notified_at = now();
                $entry->save();

                if ($ticketType === null || $ticketType->isFree()) {
                    $registration->status = RegistrationStatus::Confirmed;
                    $registration->confirmed_at = now();
                    $registration->waitlist_position = null;
                    $registration->save();

                    if ($ticketType !== null) {
                        $ticketType->quantity_sold = (int) $ticketType->quantity_sold + $entry->quantity;
                        $ticketType->save();
                    }

                    $this->tickets->issueFor($registration);

                    \App\Ems\Events\RegistrationCreated::dispatch($registration, auth()->user());

                    $this->communications->sendRegistrationBundle(
                        $registration->loadMissing(['event', 'ticketType', 'tickets', 'order'])
                    );
                } else {
                    $expiryHours = (int) config('ems.waitlist.promotion_expiry_hours', 24);

                    $registration->status = RegistrationStatus::Pending;
                    $registration->waitlist_position = null;
                    $registration->promoted_expires_at = now()->addHours($expiryHours);
                    $registration->save();

                    $this->communications->sendWaitlistPromoted(
                        $registration->loadMissing(['event', 'ticketType', 'tickets', 'order'])
                    );
                }

                $promoted++;
            }

            if ($promoted > 0) {
                $this->resequence($locked);

                Log::channel((string) config('ems.logging.channel', 'ems'))
                    ->info('ems.waitlist.promoted', [
                        'event_uuid' => $locked->uuid,
                        'count' => $promoted,
                    ]);
            }
        });

        return $promoted;
    }

    public function expireStale(): int
    {
        $expiredCount = 0;

        $staleRegistrations = Registration::query()
            ->where('status', RegistrationStatus::Pending->value)
            ->whereNotNull('promoted_expires_at')
            ->where('promoted_expires_at', '<=', now())
            ->get();

        foreach ($staleRegistrations as $reg) {
            DB::transaction(function () use ($reg, &$expiredCount): void {
                // 1. Lock Event first (Canonical order)
                $event = Event::query()->withTrashed()->whereKey($reg->event_id)->lockForUpdate()->first();
                if ($event === null) {
                    return;
                }

                // 2. Lock WaitlistEntry second (Canonical order)
                $entry = WaitlistEntry::query()
                    ->where('registration_id', $reg->id)
                    ->lockForUpdate()
                    ->first();

                // 3. Lock Registration third (Canonical order)
                $registration = Registration::query()
                    ->whereKey($reg->id)
                    ->lockForUpdate()
                    ->first();

                if ($registration && $registration->status === RegistrationStatus::Pending) {
                    $registration->status = RegistrationStatus::Cancelled;
                    $registration->cancelled_at = now();
                    $registration->promoted_expires_at = null;
                    $registration->save();

                    if ($entry) {
                        $entry->status = WaitlistStatus::Expired;
                        $entry->left_at = now();
                        $entry->save();
                    }

                    $this->resequence($event);

                    $this->communications->sendWaitlistRemoved(
                        $registration->loadMissing(['event', 'ticketType', 'tickets', 'order'])
                    );

                    $this->promoteAvailable($event);

                    $expiredCount++;

                    Log::channel((string) config('ems.logging.channel', 'ems'))
                        ->info('ems.waitlist.promoted_expired', [
                            'registration_uuid' => $registration->uuid,
                            'event_uuid' => $event->uuid,
                        ]);
                }
            });
        }

        return $expiredCount;
    }

    private function resequence(Event $event): void
    {
        $waiting = WaitlistEntry::query()
            ->where('event_id', $event->id)
            ->waiting()
            ->lockForUpdate()
            ->get();

        $position = 1;
        foreach ($waiting as $entry) {
            if ($entry->position !== $position) {
                $entry->position = $position;
                $entry->save();
            }

            if ($entry->registration) {
                $entry->registration->waitlist_position = $position;
                $entry->registration->save();
            }

            $position++;
        }
    }
}
