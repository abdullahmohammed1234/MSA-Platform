<?php

namespace App\Ems\Services;

use App\Ems\Contracts\EventNotificationDispatcher;
use App\Ems\Contracts\TicketIssuer;
use App\Ems\Enums\NotificationType;
use App\Ems\Enums\PaymentStatus;
use App\Ems\Enums\RefundStatus;
use App\Ems\Enums\RegistrationStatus;
use App\Ems\Enums\SquareRefundStatus;
use App\Ems\Enums\TicketStatus;
use App\Ems\Enums\WaitlistStatus;
use App\Ems\Exceptions\EmsException;
use App\Ems\Models\CancellationAudit;
use App\Ems\Models\Event;
use App\Ems\Models\Payment;
use App\Ems\Models\Registration;
use App\Ems\Models\SquareRefund;
use App\Ems\Models\TicketType;
use App\Ems\Models\WaitlistEntry;
use App\Ems\Services\Notifications\EventCommunicationService;
use App\Ems\Services\Square\SquareRefundService;
use App\Ems\Support\EmsPermissions;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Production-ready registration cancellation and refund orchestration service.
 *
 * Enforces non-negotiable business rules:
 * - Free tickets: Guest (via secure token), Member, or Admin may cancel.
 * - Paid tickets: Only authorized Admin may cancel, with or without refund.
 * - Guest/Member cannot cancel paid tickets or trigger refunds.
 * - Concurrency safe, releases capacity exactly once, updates audit trails.
 */
class RegistrationCancellationService
{
    public function __construct(
        private readonly TicketIssuer $tickets,
        private readonly WaitlistService $waitlist,
        private readonly EventNotificationDispatcher $dispatcher,
        private readonly EventCommunicationService $communications,
        private readonly EmsActivityLogger $activity,
        private readonly SquareRefundService $squareRefunds,
    ) {
    }

    /**
     * Generate and persist a secure cancellation token for a free registration.
     * Returns the raw unhashed token to be embedded only in outbound email.
     */
    public function generateGuestCancellation(Registration $registration): ?string
    {
        if ($registration->isPaid()) {
            return null;
        }

        $rawToken = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $rawToken);

        $registration->cancellation_token_hash = $hashedToken;
        $registration->cancellation_token_expires_at = $this->calculateTokenExpiration($registration);
        $registration->cancellation_token_used_at = null;
        $registration->saveQuietly();

        return $rawToken;
    }

    public function guestCancellationUrl(string $rawToken): string
    {
        $base = rtrim((string) config('ems.public.frontend_url', config('app.url')), '/');

        return $base . '/cancel-registration/' . $rawToken;
    }

    /**
     * Inspect a guest cancellation token and return its display state.
     *
     * @return array{
     *     state: 'valid'|'expired'|'already_cancelled'|'invalid'|'forbidden',
     *     message: string,
     *     registration?: array<string, mixed>
     * }
     */
    public function inspectGuestToken(string $rawToken): array
    {
        $token = trim($rawToken);
        if ($token === '' || strlen($token) !== 64) {
            return [
                'state' => 'invalid',
                'message' => 'Invalid cancellation link.',
            ];
        }

        $hash = hash('sha256', $token);
        /** @var Registration|null $registration */
        $registration = Registration::query()
            ->with(['event', 'ticketType', 'tickets'])
            ->where('cancellation_token_hash', $hash)
            ->first();

        if ($registration === null) {
            return [
                'state' => 'invalid',
                'message' => 'Invalid cancellation link.',
            ];
        }

        // Independently verify that the registration is a free ticket
        if ($registration->isPaid()) {
            return [
                'state' => 'forbidden',
                'message' => 'Paid registrations cannot be cancelled through guest links. Please contact the MSA team.',
            ];
        }

        if ($registration->status === RegistrationStatus::Cancelled) {
            return [
                'state' => 'already_cancelled',
                'message' => 'This registration has already been cancelled.',
                'registration' => $this->summarizeGuestRegistration($registration),
            ];
        }

        if ($registration->cancellation_token_used_at !== null) {
            return [
                'state' => 'already_cancelled',
                'message' => 'This cancellation token has already been used.',
                'registration' => $this->summarizeGuestRegistration($registration),
            ];
        }

        if ($registration->cancellation_token_expires_at !== null && $registration->cancellation_token_expires_at->isPast()) {
            return [
                'state' => 'expired',
                'message' => 'This cancellation link has expired.',
                'registration' => $this->summarizeGuestRegistration($registration),
            ];
        }

        return [
            'state' => 'valid',
            'message' => 'Registration is eligible for cancellation.',
            'registration' => $this->summarizeGuestRegistration($registration),
        ];
    }

    /**
     * Cancel a free registration via guest token.
     */
    public function cancelByGuest(string $rawToken): Registration
    {
        $token = trim($rawToken);
        if ($token === '' || strlen($token) !== 64) {
            throw new NotFoundHttpException('Invalid cancellation token.');
        }

        $hash = hash('sha256', $token);

        /** @var Registration $registration */
        $registration = DB::transaction(function () use ($hash): Registration {
            $reg = Registration::query()
                ->where('cancellation_token_hash', $hash)
                ->lockForUpdate()
                ->first();

            if ($reg === null) {
                throw new NotFoundHttpException('Registration not found or invalid token.');
            }

            if ($reg->isPaid()) {
                throw new EmsException(
                    'Paid registrations cannot be cancelled using a guest cancellation link. Please contact the MSA team.',
                    ['registration' => ['Paid registrations cannot be cancelled online.']],
                    Response::HTTP_FORBIDDEN
                );
            }

            if ($reg->status === RegistrationStatus::Cancelled || $reg->cancellation_token_used_at !== null) {
                throw new EmsException(
                    'This registration has already been cancelled.',
                    ['registration' => ['This registration has already been cancelled.']],
                    Response::HTTP_CONFLICT
                );
            }

            if ($reg->cancellation_token_expires_at !== null && $reg->cancellation_token_expires_at->isPast()) {
                throw new EmsException(
                    'This cancellation link has expired.',
                    ['token' => ['Cancellation link expired.']],
                    Response::HTTP_UNPROCESSABLE_ENTITY
                );
            }

            // Lock event for canonical lock ordering
            $event = Event::query()->withTrashed()->whereKey($reg->event_id)->lockForUpdate()->firstOrFail();

            // Lock ticket type
            $ticketType = null;
            if ($reg->ticket_type_id) {
                $ticketType = TicketType::query()->whereKey($reg->ticket_type_id)->lockForUpdate()->first();
            }

            // Handle waitlist entry if any
            $this->releaseWaitlistIfPresent($reg);

            $reg->status = RegistrationStatus::Cancelled;
            $reg->cancelled_at = now();
            $reg->cancellation_initiator_type = 'guest';
            $reg->cancellation_token_used_at = now();
            $reg->refund_status = RefundStatus::NotApplicable;
            $reg->save();

            // Revoke tickets
            $reg->tickets()->update([
                'status' => TicketStatus::Revoked->value,
                'revoked_at' => now(),
            ]);

            // Release capacity on ticket type exactly once
            if ($ticketType !== null) {
                $ticketType->quantity_sold = max(0, $ticketType->quantity_sold - $reg->quantity);
                $ticketType->save();
            }

            // Create immutable audit trail
            $this->recordAudit([
                'registration' => $reg,
                'event' => $event,
                'ticket_type' => $ticketType,
                'initiator_type' => 'guest',
                'user_id' => null,
                'reason' => 'Guest cancelled via email link',
                'refund_requested' => false,
                'refund_status' => RefundStatus::NotApplicable,
            ]);

            // Activity logger
            $this->activity->log(
                'registration.cancelled',
                $reg,
                "Registration {$reg->reference} cancelled by guest.",
                [
                    'event_id' => $event->id,
                    'registration_uuid' => $reg->uuid,
                    'initiator_type' => 'guest',
                ]
            );

            // Notify attendee
            $this->dispatcher->notifyRegistration(
                $reg,
                NotificationType::RegistrationCancelled->value,
                ['idempotency_suffix' => 'guest_cancel', 'force' => true]
            );

            // Promote waitlist
            $this->waitlist->promoteAvailable($event);

            return $reg;
        });

        return $registration->fresh(['event', 'ticketType', 'tickets', 'order']);
    }

    /**
     * Cancel a free registration by an authenticated member.
     */
    public function cancelByMember(Registration $registration, User $user): Registration
    {
        return DB::transaction(function () use ($registration, $user): Registration {
            $event = Event::query()->withTrashed()->whereKey($registration->event_id)->lockForUpdate()->firstOrFail();

            $reg = Registration::query()->whereKey($registration->id)->lockForUpdate()->firstOrFail();

            // Verify member ownership
            $isOwner = $reg->user_id === $user->id;
            if (! $isOwner && $user->email_verified_at !== null) {
                $isOwner = strtolower(trim((string) $reg->attendee_email)) === strtolower(trim($user->email));
            }

            if (! $isOwner) {
                throw new NotFoundHttpException('Registration not found.');
            }

            if ($reg->isPaid()) {
                throw new EmsException(
                    'Paid registrations cannot be cancelled online. Please contact the MSA team to request a cancellation and refund.',
                    ['registration' => ['Paid registrations cannot be cancelled online.']],
                    Response::HTTP_FORBIDDEN
                );
            }

            if ($reg->status === RegistrationStatus::Cancelled) {
                return $reg;
            }

            if ($reg->status === RegistrationStatus::Refunded) {
                throw new EmsException(
                    'This registration has already been refunded and cannot be cancelled.',
                    ['registration' => ['This registration has already been refunded.']],
                    Response::HTTP_CONFLICT
                );
            }

            $ticketType = null;
            if ($reg->ticket_type_id) {
                $ticketType = TicketType::query()->whereKey($reg->ticket_type_id)->lockForUpdate()->first();
            }

            $this->releaseWaitlistIfPresent($reg);

            $reg->status = RegistrationStatus::Cancelled;
            $reg->cancelled_at = now();
            $reg->cancellation_initiator_type = 'member';
            $reg->cancelled_by_id = $user->id;
            $reg->refund_status = RefundStatus::NotApplicable;
            $reg->save();

            $reg->tickets()->update([
                'status' => TicketStatus::Revoked->value,
                'revoked_at' => now(),
            ]);

            if ($ticketType !== null) {
                $ticketType->quantity_sold = max(0, $ticketType->quantity_sold - $reg->quantity);
                $ticketType->save();
            }

            $this->recordAudit([
                'registration' => $reg,
                'event' => $event,
                'ticket_type' => $ticketType,
                'initiator_type' => 'member',
                'user_id' => $user->id,
                'reason' => 'Member self-cancellation',
                'refund_requested' => false,
                'refund_status' => RefundStatus::NotApplicable,
            ]);

            $this->activity->log(
                'registration.cancelled',
                $reg,
                "Registration {$reg->reference} cancelled by member {$user->name}.",
                [
                    'event_id' => $event->id,
                    'registration_uuid' => $reg->uuid,
                    'initiator_type' => 'member',
                    'user_id' => $user->id,
                ]
            );

            $this->dispatcher->notifyRegistration(
                $reg,
                NotificationType::RegistrationCancelled->value,
                ['idempotency_suffix' => 'member_cancel', 'force' => true]
            );

            $this->waitlist->promoteAvailable($event);

            return $reg;
        });
    }

    /**
     * Cancel a free or paid registration by an administrator, with or without refund.
     *
     * @return array{
     *     registration: Registration,
     *     audit: CancellationAudit,
     *     refund: SquareRefund|null,
     *     refund_status: string,
     *     refund_message?: string
     * }
     */
    public function cancelByAdmin(
        Registration $registration,
        User $admin,
        string $reason,
        bool $issueRefund = false,
        ?float $refundAmount = null
    ): array {
        $reason = trim($reason);
        if (strlen($reason) < 3) {
            throw new EmsException(
                'A cancellation reason is required (minimum 3 characters).',
                ['reason' => ['A cancellation reason is required.']],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        // Refund permission must be enforced independently
        if ($issueRefund && ! $admin->hasPermission(EmsPermissions::PAYMENTS_REFUND)) {
            abort(Response::HTTP_FORBIDDEN, 'Unauthorized. Required permission: payments.refund to issue a refund.');
        }

        $paymentToRefund = null;
        $resolvedRefundAmount = null;

        // Perform cancellation inside transaction
        /** @var array{registration: Registration, audit: CancellationAudit, payment: Payment|null, refund_amount: float|null} $cancelResult */
        $cancelResult = DB::transaction(function () use (
            $registration,
            $admin,
            $reason,
            $issueRefund,
            $refundAmount
        ): array {
            $event = Event::query()->withTrashed()->whereKey($registration->event_id)->lockForUpdate()->firstOrFail();
            $reg = Registration::query()->whereKey($registration->id)->lockForUpdate()->firstOrFail();

            if ($reg->status === RegistrationStatus::Cancelled) {
                throw new EmsException(
                    'This registration is already cancelled.',
                    ['registration' => ['This registration has already been cancelled.']],
                    Response::HTTP_CONFLICT
                );
            }

            $ticketType = null;
            if ($reg->ticket_type_id) {
                $ticketType = TicketType::query()->whereKey($reg->ticket_type_id)->lockForUpdate()->first();
            }

            $isFree = $reg->isFree();
            $payment = null;
            $toRefund = 0.0;

            if ($isFree) {
                $refundStatus = RefundStatus::NotApplicable;
            } elseif (! $issueRefund) {
                $refundStatus = RefundStatus::NotRequested;
            } else {
                // Paid and refund requested
                $payment = $reg->settledPayment
                    ?? Payment::query()
                        ->where('registration_id', $reg->id)
                        ->whereIn('status', [PaymentStatus::Paid->value, PaymentStatus::PartiallyRefunded->value])
                        ->latest('id')
                        ->first()
                    ?? ($reg->order_id
                        ? Payment::query()
                            ->where('order_id', $reg->order_id)
                            ->whereIn('status', [PaymentStatus::Paid->value, PaymentStatus::PartiallyRefunded->value])
                            ->latest('id')
                            ->first()
                        : null);

                if ($payment === null) {
                    throw new EmsException(
                        'No refundable settled payment found for this paid registration.',
                        ['payment' => ['No settled payment exists to refund.']],
                        Response::HTTP_CONFLICT
                    );
                }

                $alreadyRefunded = (float) $payment->amount_refunded;
                $remainingRefundable = round(((float) $payment->amount) - $alreadyRefunded, 2);

                if ($remainingRefundable <= 0) {
                    throw new EmsException(
                        'This payment has already been fully refunded.',
                        ['payment' => ['No refundable balance remaining.']],
                        Response::HTTP_CONFLICT
                    );
                }

                $toRefund = $refundAmount === null ? $remainingRefundable : round($refundAmount, 2);
                if ($toRefund <= 0 || $toRefund > $remainingRefundable) {
                    throw new EmsException(
                        'Refund amount is invalid.',
                        ['refund_amount' => ['Must be greater than 0 and at most ' . number_format($remainingRefundable, 2, '.', '')]],
                        Response::HTTP_UNPROCESSABLE_ENTITY
                    );
                }

                $refundStatus = RefundStatus::Pending;
            }

            $this->releaseWaitlistIfPresent($reg);

            $reg->status = RegistrationStatus::Cancelled;
            $reg->cancelled_at = now();
            $reg->cancellation_reason = $reason;
            $reg->cancellation_initiator_type = 'admin';
            $reg->cancelled_by_id = $admin->id;
            $reg->refund_status = $refundStatus;
            if ($toRefund > 0) {
                $reg->refund_amount = $toRefund;
            }
            $reg->save();

            // Revoke tickets
            $reg->tickets()->update([
                'status' => TicketStatus::Revoked->value,
                'revoked_at' => now(),
            ]);

            // Release inventory / capacity
            if ($ticketType !== null) {
                $ticketType->quantity_sold = max(0, $ticketType->quantity_sold - $reg->quantity);
                $ticketType->save();
            }

            $audit = $this->recordAudit([
                'registration' => $reg,
                'event' => $event,
                'ticket_type' => $ticketType,
                'initiator_type' => 'admin',
                'user_id' => $admin->id,
                'reason' => $reason,
                'refund_requested' => $issueRefund && ! $isFree,
                'refund_amount' => $toRefund > 0 ? $toRefund : null,
                'refund_status' => $refundStatus,
            ]);

            $this->activity->log(
                'registration.cancelled',
                $reg,
                "Registration {$reg->reference} cancelled by admin {$admin->name}. Reason: {$reason}",
                [
                    'event_id' => $event->id,
                    'registration_uuid' => $reg->uuid,
                    'initiator_type' => 'admin',
                    'user_id' => $admin->id,
                    'refund_requested' => $issueRefund && ! $isFree,
                    'refund_amount' => $toRefund,
                ]
            );

            // Send registration cancellation notice to attendee
            $this->dispatcher->notifyRegistration(
                $reg,
                NotificationType::RegistrationCancelled->value,
                [
                    'cancellation_reason' => $reason,
                    'idempotency_suffix' => 'admin_cancel:' . now()->timestamp,
                    'force' => true,
                ]
            );

            $this->waitlist->promoteAvailable($event);

            return [
                'registration' => $reg,
                'audit' => $audit,
                'payment' => $payment,
                'refund_amount' => $toRefund > 0 ? $toRefund : null,
            ];
        });

        $reg = $cancelResult['registration'];
        $audit = $cancelResult['audit'];
        $payment = $cancelResult['payment'];
        $amount = $cancelResult['refund_amount'];

        $squareRefund = null;
        $refundMessage = null;

        // If refund was requested for a paid ticket, submit to payment provider
        if ($issueRefund && $payment !== null && $amount !== null) {
            try {
                $squareRefund = $this->squareRefunds->refund($payment, $amount, $reason, $admin);

                $audit->square_refund_id = $squareRefund->id;
                $audit->provider_refund_id = $squareRefund->provider_refund_id;
                $isCompleted = $squareRefund->status === SquareRefundStatus::Completed;
                $audit->refund_status = $isCompleted
                    ? RefundStatus::Succeeded
                    : RefundStatus::Pending;
                $audit->refunded_at = $isCompleted ? now() : null;
                $audit->save();

                $reg->refund_status = $audit->refund_status;
                $reg->save();

                $refundMessage = $isCompleted
                    ? 'Refund successfully processed by payment gateway.'
                    : 'Refund submitted to payment gateway (pending completion).';
            } catch (\Throwable $e) {
                // Gracefully handle payment provider failure
                Log::channel((string) config('ems.logging.channel', 'ems'))
                    ->error('ems.cancellation.refund_failed', [
                        'registration_uuid' => $reg->uuid,
                        'payment_id' => $payment->id,
                        'error' => $e->getMessage(),
                    ]);

                $audit->refund_status = RefundStatus::Failed;
                $audit->metadata = array_merge($audit->metadata ?? [], [
                    'gateway_error' => $e->getMessage(),
                ]);
                $audit->save();

                $reg->refund_status = RefundStatus::Failed;
                $reg->save();

                $refundMessage = 'Registration cancelled, but the payment refund failed: ' . $e->getMessage();
            }
        }

        return [
            'registration' => $reg->fresh(['event', 'ticketType', 'tickets', 'order']),
            'audit' => $audit->fresh(['user', 'squareRefund']),
            'refund' => $squareRefund,
            'refund_status' => $reg->refund_status->value,
            'refund_message' => $refundMessage,
        ];
    }

    /**
     * Retrieve cancellation and refund history for an event.
     */
    public function getEventCancellationHistory(Event $event): \Illuminate\Database\Eloquent\Collection
    {
        return CancellationAudit::query()
            ->where('event_id', $event->id)
            ->with(['user:id,name,email', 'registration', 'ticketType', 'squareRefund'])
            ->orderBy('cancelled_at', 'desc')
            ->get();
    }

    /**
     * Retrieve cancellation and refund history for a specific registration.
     */
    public function getRegistrationCancellationHistory(Registration $registration): \Illuminate\Database\Eloquent\Collection
    {
        return CancellationAudit::query()
            ->where('registration_id', $registration->id)
            ->with(['user:id,name,email', 'squareRefund'])
            ->orderBy('cancelled_at', 'desc')
            ->get();
    }

    private function releaseWaitlistIfPresent(Registration $registration): void
    {
        if ($registration->status === RegistrationStatus::Waitlisted) {
            $entry = WaitlistEntry::query()
                ->where('registration_id', $registration->id)
                ->where('status', WaitlistStatus::Waiting->value)
                ->lockForUpdate()
                ->first();

            if ($entry !== null) {
                $entry->status = WaitlistStatus::Left;
                $entry->left_at = now();
                $entry->save();
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function recordAudit(array $data): CancellationAudit
    {
        /** @var Registration $registration */
        $registration = $data['registration'];
        /** @var Event $event */
        $event = $data['event'];
        /** @var TicketType|null $ticketType */
        $ticketType = $data['ticket_type'] ?? null;

        $audit = new CancellationAudit();
        $audit->uuid = (string) Str::uuid();
        $audit->registration_id = $registration->id;
        $audit->event_id = $event->id;
        $audit->ticket_type_id = $ticketType?->id;
        $audit->ticket_type_name = $ticketType?->name ?? ($registration->ticketType?->name);
        $audit->attendee_name = $registration->attendee_name;
        $audit->attendee_email = $registration->attendee_email;
        $audit->initiator_type = (string) $data['initiator_type'];
        $audit->user_id = $data['user_id'] ?? null;
        $audit->reason = $data['reason'] ?? null;
        $audit->refund_requested = (bool) ($data['refund_requested'] ?? false);
        $audit->refund_amount = isset($data['refund_amount']) ? (float) $data['refund_amount'] : null;
        $audit->refund_status = $data['refund_status'] instanceof RefundStatus
            ? $data['refund_status']
            : RefundStatus::tryFrom((string) ($data['refund_status'] ?? 'not_applicable')) ?? RefundStatus::NotApplicable;
        $audit->provider_refund_id = $data['provider_refund_id'] ?? null;
        $audit->square_refund_id = $data['square_refund_id'] ?? null;
        $audit->cancelled_at = now();
        $audit->save();

        return $audit;
    }

    private function calculateTokenExpiration(Registration $registration): Carbon
    {
        $event = $registration->event;
        if ($event && $event->start_at) {
            return $event->start_at;
        }

        return now()->addDays(30);
    }

    /**
     * @return array<string, mixed>
     */
    private function summarizeGuestRegistration(Registration $registration): array
    {
        return [
            'reference' => $registration->reference,
            'attendee_name' => $registration->attendee_name,
            'attendee_email_masked' => $this->maskEmail($registration->attendee_email),
            'ticket_type_name' => $registration->ticketType?->name ?? 'General Admission',
            'quantity' => $registration->quantity,
            'status' => $registration->status->value,
            'is_cancelled' => $registration->status === RegistrationStatus::Cancelled,
            'cancelled_at' => $registration->cancelled_at?->toIso8601String(),
            'event' => [
                'name' => $registration->event?->name,
                'slug' => $registration->event?->slug,
                'start_at' => $registration->event?->start_at?->toIso8601String(),
                'end_at' => $registration->event?->end_at?->toIso8601String(),
                'location' => $registration->event?->location,
            ],
        ];
    }

    private function maskEmail(?string $email): string
    {
        if (! $email || ! str_contains($email, '@')) {
            return '***@***.***';
        }

        [$name, $domain] = explode('@', $email, 2);
        $maskedName = strlen($name) <= 2
            ? substr($name, 0, 1) . '*'
            : substr($name, 0, 1) . str_repeat('*', max(1, strlen($name) - 2)) . substr($name, -1);

        return $maskedName . '@' . $domain;
    }
}
