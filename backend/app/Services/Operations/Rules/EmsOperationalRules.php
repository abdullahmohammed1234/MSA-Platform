<?php

namespace App\Services\Operations\Rules;

use App\Ems\Models\Event as EmsEvent;
use App\Ems\Models\Payment;
use App\Ems\Models\Registration;
use App\Services\Operations\OperationalAlertService;
use Illuminate\Support\Facades\Schema;

class EmsOperationalRules
{
    public function __construct(
        private OperationalAlertService $alertService
    ) {}

    public function detect(): int
    {
        if (! Schema::hasTable('ems_registrations') || ! Schema::hasTable('ems_payments')) {
            return 0;
        }

        $detected = 0;

        // 1. Rule: Payment settled but ticket missing
        $paidRegsWithoutTickets = Registration::where('status', '!=', 'cancelled')
            ->whereHas('payments', function ($q) {
                $q->where('status', 'paid');
            })
            ->doesntHave('tickets')
            ->with(['event', 'payments' => function ($q) {
                $q->where('status', 'paid')->latest();
            }])
            ->get();

        foreach ($paidRegsWithoutTickets as $reg) {
            $payment = $reg->payments->first();
            $method = $payment?->payment_method ?? 'unknown';

            $ruleKey = in_array($method, ['square_pos', 'external_square', 'manual_override'])
                ? 'ems_reconciled_square_ticket_missing'
                : 'ems_payment_ticket_mismatch';

            $this->alertService->upsertAlert([
                'category' => 'ems',
                'severity' => 'high',
                'title' => "Ticket Issuance Missing for Paid Registration #{$reg->reference}",
                'description' => "Registration #{$reg->reference} for event '{$reg->event?->name}' has settled payment via {$method} but no ticket was issued.",
                'source_type' => 'Registration',
                'source_id' => (string) $reg->id,
                'rule_key' => $ruleKey,
                'action_url' => $reg->event ? "/ems/events/{$reg->event->uuid}" : '/ems/events',
                'metadata' => [
                    'registration_id' => $reg->id,
                    'registration_reference' => $reg->reference,
                    'attendee_name' => $reg->attendee_name,
                    'event_name' => $reg->event?->name,
                    'payment_method' => $method,
                ],
            ]);
            $detected++;
        }

        // 2. Rule: Event overbooked (Occupying capacity > capacity)
        if (Schema::hasTable('ems_events')) {
            $overbookedEvents = EmsEvent::whereNotNull('capacity')
                ->where('capacity', '>', 0)
                ->get()
                ->filter(function ($event) {
                    $occupied = $event->registrations()->occupyingCapacity()->count();

                    return $occupied > $event->capacity;
                });

            foreach ($overbookedEvents as $event) {
                $occupied = $event->registrations()->occupyingCapacity()->count();
                $this->alertService->upsertAlert([
                    'category' => 'ems',
                    'severity' => 'medium',
                    'title' => "Event Overbooked: {$event->name}",
                    'description' => "Event '{$event->name}' capacity is {$event->capacity}, but has {$occupied} confirmed registrations.",
                    'source_type' => 'Event',
                    'source_id' => (string) $event->id,
                    'rule_key' => 'ems_event_overbooked',
                    'action_url' => "/ems/events/{$event->uuid}",
                    'metadata' => [
                        'event_id' => $event->id,
                        'event_name' => $event->name,
                        'capacity' => $event->capacity,
                        'occupied' => $occupied,
                    ],
                ]);
                $detected++;
            }
        }

        return $detected;
    }
}
