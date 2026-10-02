<?php

namespace App\Ems\Services;

use App\Ems\Contracts\TicketIssuer;
use App\Ems\Enums\OrderStatus;
use App\Ems\Enums\PaymentProvider;
use App\Ems\Enums\PaymentStatus;
use App\Ems\Enums\RegistrationStatus;
use App\Ems\Enums\RegistrationType;
use App\Ems\Events\RegistrationCreated;
use App\Ems\Exceptions\CapacityExceededException;
use App\Ems\Exceptions\EmsException;
use App\Ems\Exceptions\TicketUnavailableException;
use App\Ems\Models\Event;
use App\Ems\Models\Order;
use App\Ems\Models\OrderItem;
use App\Ems\Models\Payment;
use App\Ems\Models\Registration;
use App\Ems\Models\TicketType;
use App\Ems\Services\Notifications\EventCommunicationService;
use App\Ems\Services\Ticketing\TicketCodeGenerator;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ManualRegistrationService
{
    public function __construct(
        private readonly TicketCodeGenerator $codes,
        private readonly TicketIssuer $tickets,
        private readonly EventCommunicationService $communications,
    ) {
    }

    /**
     * Register a cash buyer or invited guest manually by an admin.
     *
     * @param array{
     *     first_name: string,
     *     last_name: string,
     *     email: string,
     *     phone?: string|null,
     *     registration_type: string,
     *     amount?: float|null,
     *     ticket_type_id?: string|null,
     *     quantity?: int,
     *     notes?: string|null
     * } $data
     * @return array{registration: Registration, order: Order, payment: Payment}
     */
    public function registerManual(Event $event, array $data, User $actor): array
    {
        $firstName = trim($data['first_name']);
        $lastName = trim($data['last_name']);
        $attendeeName = trim($firstName . ' ' . $lastName);
        $email = !empty($data['email']) ? strtolower(trim($data['email'])) : null;
        $quantity = max(1, (int) ($data['quantity'] ?? 1));
        $registrationType = $data['registration_type'] ?? 'cash';

        return DB::transaction(function () use ($event, $data, $actor, $firstName, $lastName, $attendeeName, $email, $quantity, $registrationType) {
            /** @var Event $locked */
            $locked = Event::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();

            $ticketType = null;
            if (!empty($data['ticket_type_id'])) {
                $ticketType = TicketType::query()
                    ->where('event_id', $locked->id)
                    ->where(function ($q) use ($data) {
                        $q->where('uuid', $data['ticket_type_id']);
                        if (is_numeric($data['ticket_type_id'])) {
                            $q->orWhere('id', (int) $data['ticket_type_id']);
                        }
                    })
                    ->lockForUpdate()
                    ->first();

                if (!$ticketType) {
                    throw new EmsException(
                        'The selected ticket type was not found.',
                        ['ticket_type_id' => ['Invalid ticket type.']],
                        Response::HTTP_UNPROCESSABLE_ENTITY
                    );
                }

                if (!$ticketType->hasAvailableQuantity($quantity)) {
                    throw TicketUnavailableException::insufficient((int) $ticketType->remainingQuantity());
                }
            } else {
                $firstType = $locked->ticketTypes()->where('is_active', true)->first();
                if ($firstType) {
                    $ticketType = TicketType::query()->whereKey($firstType->id)->lockForUpdate()->first();
                }
            }

            if (!$locked->hasAvailableCapacity($quantity)) {
                throw CapacityExceededException::make($locked->remainingCapacity());
            }

            $currency = $ticketType?->currency ?? (string) config('ems.defaults.currency', 'CAD');

            if ($registrationType === 'cash') {
                $unitPrice = isset($data['amount']) ? (float) $data['amount'] / $quantity : ($ticketType ? (float) $ticketType->price : 0.0);
                $totalAmount = max(0.0, isset($data['amount']) ? (float) $data['amount'] : $unitPrice * $quantity);
                $paymentProvider = PaymentProvider::Cash;
                $regType = RegistrationType::Paid;
            } else {
                $unitPrice = 0.0;
                $totalAmount = 0.0;
                $paymentProvider = PaymentProvider::GuestInvite;
                $regType = RegistrationType::Free;
            }

            $order = new Order();
            $order->reference = $this->codes->orderReference();
            $order->event_id = $locked->id;
            $order->user_id = null;
            $matchedUser = $email !== null ? User::where('email', $email)->first() : null;
            if ($matchedUser) {
                $order->user_id = $matchedUser->id;
            }
            $order->buyer_name = $attendeeName;
            $order->buyer_email = $email;
            $order->buyer_phone = isset($data['phone']) ? trim((string) $data['phone']) : null;
            $order->total_amount = $totalAmount;
            $order->currency = $currency;
            $order->status = OrderStatus::Completed;
            $order->completed_at = now();
            $order->source_channel = 'manual_admin';
            $order->save();

            if ($ticketType !== null) {
                $item = new OrderItem();
                $item->order_id = $order->id;
                $item->ticket_type_id = $ticketType->id;
                $item->name = $ticketType->name;
                $item->quantity = $quantity;
                $item->unit_price = $unitPrice;
                $item->line_total = $totalAmount;
                $item->currency = $currency;
                $item->save();
            }

            $metadata = [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'registration_source' => 'manual_admin',
                'acquisition_type' => $registrationType,
                'registered_by_user_id' => $actor->id,
                'registered_by_name' => $actor->name,
                'notes' => $data['notes'] ?? null,
            ];

            $registration = new Registration();
            $registration->reference = $this->codes->registrationReference();
            $registration->event_id = $locked->id;
            $registration->user_id = $order->user_id;
            $registration->ticket_type_id = $ticketType?->id;
            $registration->order_id = $order->id;
            $registration->attendee_name = $attendeeName;
            $registration->attendee_email = $email;
            $registration->attendee_phone = isset($data['phone']) ? trim((string) $data['phone']) : null;
            $registration->notes = isset($data['notes']) ? trim((string) $data['notes']) : null;
            $registration->status = RegistrationStatus::Confirmed;
            $registration->type = $regType;
            $registration->quantity = $quantity;
            $registration->amount_due = 0;
            $registration->currency = $currency;
            $registration->registered_at = now();
            $registration->confirmed_at = now();
            $registration->metadata = $metadata;
            $registration->save();

            $payment = new Payment();
            $payment->uuid = (string) Str::uuid();
            $payment->order_id = $order->id;
            $payment->registration_id = $registration->id;
            $payment->amount = $totalAmount;
            $payment->currency = $currency;
            $payment->provider = $paymentProvider;
            $payment->status = PaymentStatus::Paid;
            $payment->paid_at = now();
            $payment->provider_payment_id = 'MANUAL_' . strtoupper($registrationType) . '_' . strtoupper(Str::random(8));
            $payment->metadata = [
                'registered_by_user_id' => $actor->id,
                'registered_by_name' => $actor->name,
                'registration_source' => 'manual_admin',
                'acquisition_type' => $registrationType,
                'notes' => $data['notes'] ?? null,
            ];
            $payment->save();

            if ($ticketType !== null) {
                $ticketType->quantity_sold += $quantity;
                $ticketType->save();
            }

            $this->tickets->issueFor($registration);

            $freshRegistration = $registration->fresh(['tickets.event.category', 'event.category', 'event.organizer', 'ticketType', 'order']);

            RegistrationCreated::dispatch($freshRegistration, $actor);
            if (!empty($freshRegistration->attendee_email)) {
                $this->communications->sendRegistrationBundle($freshRegistration);
            }

            Log::channel((string) config('ems.logging.channel', 'ems'))
                ->info('ems.manual_registration.created', [
                    'event_uuid' => $locked->uuid,
                    'registration_uuid' => $registration->uuid,
                    'type' => $registrationType,
                    'actor_id' => $actor->id,
                ]);

            return [
                'registration' => $freshRegistration,
                'order' => $order,
                'payment' => $payment,
            ];
        });
    }
}
