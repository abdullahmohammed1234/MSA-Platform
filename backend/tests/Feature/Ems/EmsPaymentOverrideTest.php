<?php

namespace Tests\Feature\Ems;

use App\Ems\Models\Event;
use App\Ems\Models\Registration;
use App\Ems\Models\TicketType;
use App\Ems\Enums\RegistrationStatus;
use App\Ems\Support\EmsRoles;

class EmsPaymentOverrideTest extends EmsTestCase
{
    public function test_super_admin_can_override_payment_and_issue_ticket(): void
    {
        $superAdmin = $this->emsUser(EmsRoles::SUPER_ADMIN);

        $event = Event::factory()->create();
        $ticketType = TicketType::factory()->create(['event_id' => $event->id, 'quantity' => 10]);

        $registration = Registration::factory()->create([
            'event_id' => $event->id,
            'ticket_type_id' => $ticketType->id,
            'status' => RegistrationStatus::AwaitingPayment->value,
            'user_id' => $superAdmin->id,
        ]);

        $response = $this->actingAsEms($superAdmin)
            ->postJson('/api/v1/ems/admin/payments/override', [
                'registration_id' => $registration->id,
                'payment_method' => 'cash',
                'reference_id' => 'CASH-REC-1002',
                'reason' => 'Collected in-person cash payment at registration desk.',
            ]);

        $response->assertStatus(200);
        $this->assertEquals(RegistrationStatus::Confirmed->value, $registration->fresh()->status->value);
        $this->assertCount(1, $registration->fresh()->tickets);
    }

    public function test_reconcile_external_square_payment_prevents_duplicate(): void
    {
        $admin = $this->emsUser(EmsRoles::SUPER_ADMIN);

        $event = Event::factory()->create();
        $ticketType = TicketType::factory()->create(['event_id' => $event->id, 'quantity' => 10]);

        $registration = Registration::factory()->create([
            'event_id' => $event->id,
            'ticket_type_id' => $ticketType->id,
            'status' => RegistrationStatus::AwaitingPayment->value,
        ]);

        $payload = [
            'registration_id' => $registration->id,
            'external_transaction_id' => 'SQ_POS_TXN_998811',
            'payment_method' => 'square_pos',
            'reason' => 'Reconciling Square POS terminal transaction.',
        ];

        $res1 = $this->actingAsEms($admin)
            ->postJson('/api/v1/ems/admin/payments/reconcile-external', $payload);
        $res1->assertStatus(200);

        // Duplicate reconciliation must be rejected with 409 Conflict
        $res2 = $this->actingAsEms($admin)
            ->postJson('/api/v1/ems/admin/payments/reconcile-external', $payload);
        $res2->assertStatus(409);
    }
}
