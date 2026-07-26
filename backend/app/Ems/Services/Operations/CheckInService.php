<?php

namespace App\Ems\Services\Operations;

use App\Ems\Contracts\TicketIssuer;
use App\Ems\Enums\CheckInMethod;
use App\Ems\Enums\OrderStatus;
use App\Ems\Enums\PaymentStatus;
use App\Ems\Enums\RegistrationStatus;
use App\Ems\Enums\RegistrationType;
use App\Ems\Enums\TicketStatus;
use App\Ems\Events\AttendeeCheckedIn;
use App\Ems\Events\CheckInUndone;
use App\Ems\Events\RegistrationCreated;
use App\Ems\Events\WalkInRegistered;
use App\Ems\Exceptions\CapacityExceededException;
use App\Ems\Exceptions\CheckInException;
use App\Ems\Exceptions\DuplicateRegistrationException;
use App\Ems\Exceptions\EmsException;
use App\Ems\Exceptions\TicketUnavailableException;
use App\Ems\Models\CheckIn;
use App\Ems\Models\CheckInAudit;
use App\Ems\Models\Event;
use App\Ems\Models\Order;
use App\Ems\Models\OrderItem;
use App\Ems\Models\Registration;
use App\Ems\Models\Ticket;
use App\Ems\Models\TicketType;
use App\Ems\Services\CheckoutService;
use App\Ems\Services\Ticketing\TicketCodeGenerator;
use App\Ems\Support\EmsPermissions;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class CheckInService
{
    public function __construct(
        private readonly TicketValidationService $validator,
        private readonly TicketIssuer $tickets,
        private readonly TicketCodeGenerator $codes,
        private readonly CheckoutService $checkout,
    ) {
    }

    /**
     * @return array{check_in: CheckIn, ticket: Ticket, registration: Registration}
     */
    public function checkInByCode(
        Event $event,
        string $rawCode,
        User $staff,
        CheckInMethod $method = CheckInMethod::QrScan,
        ?string $device = null,
        bool $override = false,
        ?string $ip = null,
    ): array {
        try {
            $validation = $this->validator->validate($event, $rawCode);
        } catch (CheckInException $e) {
            $this->auditFailure($event, $staff, $e, $method, $device, $ip, $rawCode);

            if ($e->resultCode() === 'already_checked_in' && $override) {
                if (! $staff->hasPermission(EmsPermissions::CHECK_INS_OVERRIDE)) {
                    throw $e;
                }

                // Override re-check-in is not allowed while a row still exists —
                // undo first, then check in again. Surface a clear message.
                throw new EmsException(
                    'Already checked in. Undo the previous check-in before overriding.',
                    ['check_in' => ['Undo required before override.']],
                    Response::HTTP_CONFLICT
                );
            }

            throw $e;
        }

        /** @var Ticket $ticket */
        $ticket = $validation['ticket'];
        /** @var Registration $registration */
        $registration = $validation['registration'];

        return $this->redeem($event, $ticket, $registration, $staff, $method, $device, $ip);
    }

    /**
     * @return array{check_in: CheckIn, ticket: Ticket|null, registration: Registration}
     */
    public function manualCheckIn(
        Event $event,
        User $staff,
        ?string $registrationUuid = null,
        ?string $ticketCode = null,
        ?string $device = null,
        ?string $ip = null,
    ): array {
        if ($ticketCode) {
            return $this->checkInByCode(
                $event,
                $ticketCode,
                $staff,
                CheckInMethod::Manual,
                $device,
                false,
                $ip
            );
        }

        if (! $registrationUuid) {
            throw new EmsException(
                'Provide a registration or ticket code.',
                ['registration_uuid' => ['Required when ticket_code is omitted.']],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $registration = Registration::query()
            ->where('event_id', $event->id)
            ->where('uuid', $registrationUuid)
            ->with(['tickets.checkIn', 'payments'])
            ->firstOrFail();

        $ticket = $registration->tickets->first(fn (Ticket $t) => $t->checkIn === null && $t->status === TicketStatus::Issued)
            ?? $registration->tickets->first();

        if ($ticket === null) {
            // Issue a ticket for confirmed free walk-ups that somehow lack one.
            if ($registration->status === RegistrationStatus::Confirmed) {
                $ticket = $this->tickets->issueFor($registration)->first();
            }
        }

        if ($ticket === null) {
            throw CheckInException::ticketNotFound();
        }

        return $this->checkInByCode(
            $event,
            $ticket->code,
            $staff,
            CheckInMethod::Manual,
            $device,
            false,
            $ip
        );
    }

    /**
     * @param  array{
     *     attendee_name: string,
     *     attendee_email?: string|null,
     *     attendee_phone?: string|null,
     *     ticket_type_id: string,
     *     check_in?: bool,
     *     is_member?: bool
     * }  $data
     * @return array{
     *     registration: Registration,
     *     tickets: \Illuminate\Support\Collection,
     *     check_in: CheckIn|null,
     *     checkout_url: string|null
     * }
     */
    public function walkIn(Event $event, array $data, User $staff, ?string $ip = null): array
    {
        $ticketType = TicketType::query()
            ->where('event_id', $event->id)
            ->where('uuid', $data['ticket_type_id'])
            ->firstOrFail();

        $name = trim($data['attendee_name']);
        $email = strtolower(trim((string) ($data['attendee_email'] ?? '')));
        if ($email === '') {
            $email = sprintf('walkin+%s@ems.local', substr(md5($name . microtime(true)), 0, 10));
        }
        $phone = isset($data['attendee_phone']) ? trim((string) $data['attendee_phone']) : null;
        $autoCheckIn = (bool) ($data['check_in'] ?? true);

        $parts = preg_split('/\s+/', $name, 2) ?: [$name];
        $firstName = $parts[0] ?? $name;
        $lastName = $parts[1] ?? 'Walk-in';

        // Paid walk-ins go through Square hosted checkout before tickets issue.
        if ((float) $ticketType->price > 0) {
            $result = $this->checkout->checkout($event, [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'phone' => $phone,
                'quantity' => 1,
                'ticket_type_id' => $ticketType->uuid,
            ], $staff);

            /** @var Registration $registration */
            $registration = $result['registration'];
            $meta = $registration->metadata ?? [];
            $meta['source'] = 'walk_in';
            $meta['is_member'] = (bool) ($data['is_member'] ?? false);
            $meta['walk_in_by'] = $staff->id;
            $registration->metadata = $meta;
            $registration->save();

            WalkInRegistered::dispatch($registration, $staff, [
                'requires_payment' => true,
            ]);

            return [
                'registration' => $registration->fresh(['ticketType', 'tickets', 'order']),
                'tickets' => collect(),
                'check_in' => null,
                'checkout_url' => $result['checkout_url'] ?? null,
            ];
        }

        $registration = DB::transaction(function () use ($event, $ticketType, $name, $email, $phone, $staff, $data, $firstName, $lastName) {
            $locked = Event::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();
            $lockedType = TicketType::query()->whereKey($ticketType->id)->lockForUpdate()->firstOrFail();

            if (! $locked->hasAvailableCapacity(1)) {
                throw CapacityExceededException::make($locked->remainingCapacity());
            }

            if (! $lockedType->hasAvailableQuantity(1)) {
                throw TicketUnavailableException::insufficient((int) $lockedType->remainingQuantity());
            }

            if (
                Registration::query()
                    ->where('event_id', $locked->id)
                    ->where('attendee_email', $email)
                    ->whereIn('status', [
                        RegistrationStatus::Pending->value,
                        RegistrationStatus::AwaitingPayment->value,
                        RegistrationStatus::Confirmed->value,
                    ])
                    ->exists()
            ) {
                throw DuplicateRegistrationException::forEmail($email);
            }

            $currency = $lockedType->currency ?? (string) config('ems.defaults.currency', 'CAD');

            $order = new Order();
            $order->reference = $this->codes->orderReference();
            $order->event_id = $locked->id;
            $order->user_id = $staff->id;
            $order->buyer_name = $name;
            $order->buyer_email = $email;
            $order->buyer_phone = $phone;
            $order->total_amount = 0;
            $order->currency = $currency;
            $order->status = OrderStatus::Completed;
            $order->completed_at = now();
            $order->metadata = ['source' => 'walk_in'];
            $order->save();

            $item = new OrderItem();
            $item->order_id = $order->id;
            $item->ticket_type_id = $lockedType->id;
            $item->name = $lockedType->name;
            $item->quantity = 1;
            $item->unit_price = 0;
            $item->line_total = 0;
            $item->currency = $currency;
            $item->save();

            $registration = new Registration();
            $registration->reference = $this->codes->registrationReference();
            $registration->event_id = $locked->id;
            $registration->user_id = null;
            $registration->ticket_type_id = $lockedType->id;
            $registration->order_id = $order->id;
            $registration->attendee_name = $name;
            $registration->attendee_email = $email;
            $registration->attendee_phone = $phone;
            $registration->status = RegistrationStatus::Confirmed;
            $registration->type = RegistrationType::Free;
            $registration->quantity = 1;
            $registration->amount_due = 0;
            $registration->currency = $currency;
            $registration->registered_at = now();
            $registration->confirmed_at = now();
            $registration->metadata = [
                'source' => 'walk_in',
                'is_member' => (bool) ($data['is_member'] ?? false),
                'walk_in_by' => $staff->id,
                'first_name' => $firstName,
                'last_name' => $lastName,
            ];
            $registration->save();

            $lockedType->quantity_sold = (int) $lockedType->quantity_sold + 1;
            $lockedType->save();

            RegistrationCreated::dispatch($registration, $staff);
            WalkInRegistered::dispatch($registration, $staff);

            return $registration;
        });

        $issued = $this->tickets->issueFor($registration->fresh());
        $checkIn = null;

        if ($autoCheckIn && $issued->isNotEmpty()) {
            $result = $this->redeem(
                $event,
                $issued->first(),
                $registration->fresh(),
                $staff,
                CheckInMethod::WalkIn,
                null,
                $ip
            );
            $checkIn = $result['check_in'];
        }

        return [
            'registration' => $registration->fresh(['ticketType', 'tickets', 'checkIns']),
            'tickets' => $issued,
            'check_in' => $checkIn,
            'checkout_url' => null,
        ];
    }

    /**
     * @return array{audit: CheckInAudit}
     */
    public function undoCheckIn(
        Event $event,
        User $staff,
        ?string $checkInUuid = null,
        ?string $ticketCode = null,
        string $reason = '',
        ?string $ip = null,
    ): array {
        $checkIn = null;

        if ($checkInUuid) {
            $checkIn = CheckIn::query()
                ->where('event_id', $event->id)
                ->where('uuid', $checkInUuid)
                ->with(['ticket', 'registration'])
                ->firstOrFail();
        } elseif ($ticketCode) {
            $code = $this->validator->extractCode($ticketCode);
            $ticket = Ticket::query()
                ->where('event_id', $event->id)
                ->where('code', $code)
                ->firstOrFail();
            $checkIn = CheckIn::query()
                ->where('event_id', $event->id)
                ->where('ticket_id', $ticket->id)
                ->with(['ticket', 'registration'])
                ->firstOrFail();
        } else {
            throw new EmsException(
                'Provide a check-in or ticket code to undo.',
                [],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        return DB::transaction(function () use ($event, $staff, $checkIn, $reason, $ip) {
            $originalAt = $checkIn->checked_in_at?->toIso8601String();
            $ticket = $checkIn->ticket;

            $audit = CheckInAudit::query()->create([
                'event_id' => $event->id,
                'ticket_id' => $checkIn->ticket_id,
                'registration_id' => $checkIn->registration_id,
                'actor_id' => $staff->id,
                'action' => 'undo',
                'method' => $checkIn->method->value,
                'result_code' => 'undone',
                'message' => 'Check-in undone.',
                'ip_address' => $ip,
                'device' => $checkIn->device,
                'context' => [
                    'reason' => $reason,
                    'original_checked_in_at' => $originalAt,
                    'original_checked_in_by' => $checkIn->checked_in_by,
                    'check_in_uuid' => $checkIn->uuid,
                    'undone_at' => now()->toIso8601String(),
                ],
            ]);

            if ($ticket) {
                $ticket->status = TicketStatus::Issued;
                $ticket->save();
            }

            $checkIn->delete();

            CheckInUndone::dispatch($audit, $staff, [
                'reason' => $reason,
                'original_checked_in_at' => $originalAt,
            ]);

            return ['audit' => $audit];
        });
    }

    /**
     * @return array{check_in: CheckIn, ticket: Ticket, registration: Registration}
     */
    private function redeem(
        Event $event,
        Ticket $ticket,
        Registration $registration,
        User $staff,
        CheckInMethod $method,
        ?string $device,
        ?string $ip,
    ): array {
        return DB::transaction(function () use ($event, $ticket, $registration, $staff, $method, $device, $ip) {
            $locked = Ticket::query()->whereKey($ticket->id)->lockForUpdate()->firstOrFail();

            if (CheckIn::query()->where('ticket_id', $locked->id)->exists()) {
                throw CheckInException::alreadyCheckedIn();
            }

            if ($locked->status !== TicketStatus::Issued) {
                throw CheckInException::inactiveTicket($locked->status->label());
            }

            $checkIn = new CheckIn();
            $checkIn->event_id = $event->id;
            $checkIn->ticket_id = $locked->id;
            $checkIn->registration_id = $registration->id;
            $checkIn->checked_in_by = $staff->id;
            $checkIn->checked_in_at = now();
            $checkIn->method = $method;
            $checkIn->device = $device;
            $checkIn->save();

            $locked->status = TicketStatus::Redeemed;
            $locked->save();

            CheckInAudit::query()->create([
                'event_id' => $event->id,
                'ticket_id' => $locked->id,
                'registration_id' => $registration->id,
                'actor_id' => $staff->id,
                'action' => 'check_in',
                'method' => $method->value,
                'result_code' => 'success',
                'message' => 'Checked in successfully.',
                'ip_address' => $ip,
                'device' => $device,
                'context' => [
                    'check_in_uuid' => $checkIn->uuid,
                    'ticket_code' => $locked->code,
                ],
            ]);

            AttendeeCheckedIn::dispatch($checkIn, $staff);

            return [
                'check_in' => $checkIn->fresh(['checkedInBy', 'ticket', 'registration']),
                'ticket' => $locked->fresh(),
                'registration' => $registration->fresh(),
            ];
        });
    }

    private function auditFailure(
        Event $event,
        User $staff,
        CheckInException $e,
        CheckInMethod $method,
        ?string $device,
        ?string $ip,
        string $rawCode,
    ): void {
        CheckInAudit::query()->create([
            'event_id' => $event->id,
            'ticket_id' => null,
            'registration_id' => null,
            'actor_id' => $staff->id,
            'action' => 'validation_failure',
            'method' => $method->value,
            'result_code' => $e->resultCode(),
            'message' => $e->getMessage(),
            'ip_address' => $ip,
            'device' => $device,
            'context' => array_merge($e->context(), [
                'raw_code' => substr($rawCode, 0, 120),
            ]),
        ]);
    }
}
