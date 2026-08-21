<?php

namespace App\Ems\Http\Resources;

use App\Ems\Enums\CheckInStatus;
use App\Ems\Enums\PaymentStatus;
use App\Ems\Enums\RegistrationStatus;
use App\Ems\Models\Event;
use App\Ems\Models\Registration;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Registration
 */
class AttendeeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $checkIn = $this->checkIns->sortByDesc('checked_in_at')->first()
            ?? $this->tickets->flatMap(fn ($t) => $t->checkIn ? collect([$t->checkIn]) : collect())->sortByDesc('checked_in_at')->first();

        $ticket = $this->tickets->first();
        $payment = $this->payments->sortByDesc('id')->first();

        $paymentStatus = $this->resolvePaymentStatus($payment);
        $checkInStatus = $this->resolveCheckInStatus($checkIn !== null, $this->event, $this->status);

        return [
            'uuid' => $this->uuid,
            'reference' => $this->reference,
            'attendee_name' => $this->attendee_name,
            'attendee_email' => $this->attendee_email,
            'attendee_phone' => $this->attendee_phone,
            'ticket_type' => $this->ticketType ? [
                'uuid' => $this->ticketType->uuid,
                'name' => $this->ticketType->name,
            ] : null,
            'ticket_code' => $ticket?->code,
            'ticket_uuid' => $ticket?->uuid,
            'qr_payload' => $ticket?->qr_payload,
            'registration_status' => $this->status->value,
            'registration_status_label' => $this->status->label(),
            'payment_status' => $paymentStatus,
            'payment_status_label' => PaymentStatus::tryFrom($paymentStatus)?->label() ?? $paymentStatus,
            'payment_uuid' => $payment?->uuid,
            'order_uuid' => $this->order?->uuid,
            'source_channel' => $payment?->source_channel ?? ($this->order?->source_channel),
            'source_channel_label' => \App\Ems\Enums\PaymentSourceChannel::tryFrom(
                (string) ($payment?->source_channel ?? $this->order?->source_channel ?? '')
            )?->label(),
            'check_in_status' => $checkInStatus->value,
            'check_in_status_label' => $checkInStatus->label(),
            'check_in_at' => $checkIn?->checked_in_at?->toIso8601String(),
            'checked_in_by' => $checkIn?->checkedInBy?->name,
            'registration_source' => $this->metadata['source'] ?? 'ems',
            'is_member' => (bool) ($this->metadata['is_member'] ?? false),
            'registered_at' => $this->registered_at?->toIso8601String(),
            'quantity' => $this->quantity,
        ];
    }

    private function resolvePaymentStatus(mixed $payment): string
    {
        if ($this->type->value === 'free' || $this->status === RegistrationStatus::Confirmed) {
            if ($payment?->status) {
                return $payment->status->value;
            }

            return PaymentStatus::Paid->value;
        }

        if ($this->status === RegistrationStatus::AwaitingPayment) {
            return PaymentStatus::Pending->value;
        }

        if ($this->status === RegistrationStatus::Refunded) {
            return PaymentStatus::Refunded->value;
        }

        if ($this->status === RegistrationStatus::Cancelled) {
            return PaymentStatus::Cancelled->value;
        }

        return $payment?->status?->value
            ?? ($this->metadata['payment_status'] ?? PaymentStatus::Pending->value);
    }

    private function resolveCheckInStatus(bool $checkedIn, ?Event $event, RegistrationStatus $status): CheckInStatus
    {
        if ($checkedIn) {
            return CheckInStatus::CheckedIn;
        }

        if ($event?->hasEnded() && $status === RegistrationStatus::Confirmed) {
            return CheckInStatus::NoShow;
        }

        return CheckInStatus::NotCheckedIn;
    }
}
