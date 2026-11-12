<?php

namespace Tests\Feature\Ems;

use App\Ems\Contracts\EventNotificationDispatcher;
use App\Ems\Enums\EventStatus;
use App\Ems\Enums\NotificationType;
use App\Ems\Enums\OrderStatus;
use App\Ems\Enums\PaymentStatus;
use App\Ems\Enums\RefundStatus;
use App\Ems\Enums\RegistrationStatus;
use App\Ems\Enums\RegistrationType;
use App\Ems\Enums\TicketStatus;
use App\Ems\Models\CancellationAudit;
use App\Ems\Models\Event;
use App\Ems\Models\Order;
use App\Ems\Models\OrderItem;
use App\Ems\Models\Payment;
use App\Ems\Models\Registration;
use App\Ems\Models\Ticket;
use App\Ems\Models\TicketType;
use App\Ems\Services\CheckoutService;
use App\Ems\Services\Operations\CheckInService;
use App\Ems\Services\RegistrationCancellationService;
use App\Ems\Services\Ticketing\DefaultTicketIssuer;
use App\Ems\Support\EmsPermissions;
use App\Ems\Support\EmsRoles;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class TicketCancellationAndRefundManagementTest extends EmsTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ems.payments.enabled' => true,
            'ems.payments.square.access_token' => 'test-token',
            'ems.payments.square.location_id' => 'LOCATION_TEST',
            'ems.payments.square.application_id' => 'app-test',
            'ems.notifications.enabled' => true,
            'queue.default' => 'sync',
            'ems.tickets.enabled' => true,
        ]);
    }

    private function createPublishedEvent(array $attributes = []): Event
    {
        $category = $this->category();

        return Event::factory()->create(array_merge([
            'category_id' => $category->id,
            'status' => EventStatus::RegistrationOpen,
            'is_public' => true,
            'capacity' => 50,
            'start_at' => now()->addDays(5),
            'end_at' => now()->addDays(5)->addHours(2),
        ], $attributes));
    }

    private function createFreeRegistration(Event $event, ?User $user = null, array $attributes = []): array
    {
        $ticketType = TicketType::factory()->create([
            'event_id' => $event->id,
            'price' => 0.00,
            'quantity' => 50,
            'quantity_sold' => 0,
        ]);

        $regData = [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane.doe@example.com',
            'quantity' => 1,
            'ticket_type_id' => $ticketType->uuid,
        ];

        /** @var CheckoutService $checkout */
        $checkout = app(CheckoutService::class);
        $registration = $checkout->registerFree($event, $regData, $user);

        return [$ticketType, $registration];
    }

    private function createPaidRegistration(Event $event, ?User $user = null, float $price = 25.00): array
    {
        $ticketType = TicketType::factory()->create([
            'event_id' => $event->id,
            'name' => 'Paid Ticket ' . Str::random(8),
            'price' => $price,
            'quantity' => 50,
            'quantity_sold' => 1,
        ]);

        $order = Order::create([
            'uuid' => (string) Str::uuid(),
            'reference' => 'ORD-' . strtoupper(Str::random(6)),
            'event_id' => $event->id,
            'user_id' => $user?->id,
            'buyer_name' => 'Paid Attendee',
            'buyer_email' => 'paid@example.com',
            'status' => OrderStatus::Completed,
            'total_amount' => $price,
            'currency' => 'CAD',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'ticket_type_id' => $ticketType->id,
            'name' => $ticketType->name,
            'quantity' => 1,
            'unit_price' => $price,
            'total_price' => $price,
            'currency' => 'CAD',
        ]);

        $registration = Registration::create([
            'uuid' => (string) Str::uuid(),
            'reference' => 'REG-' . strtoupper(Str::random(6)),
            'event_id' => $event->id,
            'user_id' => $user?->id,
            'ticket_type_id' => $ticketType->id,
            'order_id' => $order->id,
            'attendee_name' => 'Paid Attendee',
            'attendee_email' => 'paid@example.com',
            'status' => RegistrationStatus::Confirmed,
            'type' => RegistrationType::Paid,
            'quantity' => 1,
            'amount_due' => $price,
            'currency' => 'CAD',
            'registered_at' => now(),
            'confirmed_at' => now(),
        ]);

        $payment = Payment::create([
            'uuid' => (string) Str::uuid(),
            'order_id' => $order->id,
            'registration_id' => $registration->id,
            'provider' => 'square',
            'status' => PaymentStatus::Paid,
            'amount' => $price,
            'amount_refunded' => 0.00,
            'currency' => 'CAD',
            'provider_payment_id' => 'sq_pay_' . Str::random(10),
            'paid_at' => now(),
        ]);

        app(DefaultTicketIssuer::class)->issueFor($registration);

        return [$ticketType, $order, $registration, $payment];
    }

    // =========================================================================
    // 1. GUEST TESTS
    // =========================================================================

    public function test_guest_can_inspect_valid_cancellation_token(): void
    {
        $event = $this->createPublishedEvent();
        /** @var Registration $registration */
        [$ticketType, $registration] = $this->createFreeRegistration($event);

        $service = app(RegistrationCancellationService::class);
        $rawToken = $service->generateGuestCancellation($registration);

        $response = $this->getJson($this->url("public/cancel-registration/{$rawToken}"));
        $response->assertOk()
            ->assertJsonPath('data.state', 'valid')
            ->assertJsonPath('data.registration.reference', $registration->reference)
            ->assertJsonPath('data.registration.event.name', $event->name);
    }

    public function test_guest_can_cancel_an_eligible_free_ticket_without_an_account(): void
    {
        $event = $this->createPublishedEvent();
        /** @var Registration $registration */
        [$ticketType, $registration] = $this->createFreeRegistration($event);

        $this->assertSame(1, $ticketType->fresh()->quantity_sold);
        $this->assertSame(1, $event->fresh()->occupiedSeats());

        $service = app(RegistrationCancellationService::class);
        $rawToken = $service->generateGuestCancellation($registration);

        $response = $this->postJson($this->url("public/cancel-registration/{$rawToken}"));
        $response->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $regFresh = $registration->fresh();
        $this->assertSame(RegistrationStatus::Cancelled, $regFresh->status);
        $this->assertSame('guest', $regFresh->cancellation_initiator_type);
        $this->assertNotNull($regFresh->cancelled_at);

        // Capacity released exactly once
        $this->assertSame(0, $ticketType->fresh()->quantity_sold);
        $this->assertSame(0, $event->fresh()->occupiedSeats());

        // Ticket revoked
        $this->assertSame(TicketStatus::Revoked, $registration->tickets()->first()->status);

        // Audit trail exists
        $this->assertDatabaseHas('ems_cancellation_audits', [
            'registration_id' => $registration->id,
            'initiator_type' => 'guest',
            'refund_status' => 'not_applicable',
        ]);
    }

    public function test_invalid_cancellation_token_is_rejected(): void
    {
        $response = $this->getJson($this->url('public/cancel-registration/nonexistent_token_1234567890123456789012345678901234567890123456789012'));
        $response->assertOk()->assertJsonPath('data.state', 'invalid');

        $cancelResponse = $this->postJson($this->url('public/cancel-registration/nonexistent_token_1234567890123456789012345678901234567890123456789012'));
        $cancelResponse->assertNotFound();
    }

    public function test_expired_cancellation_token_is_rejected(): void
    {
        $event = $this->createPublishedEvent();
        /** @var Registration $registration */
        [$ticketType, $registration] = $this->createFreeRegistration($event);

        $service = app(RegistrationCancellationService::class);
        $rawToken = $service->generateGuestCancellation($registration);

        // Expire token
        $registration->cancellation_token_expires_at = now()->subHour();
        $registration->saveQuietly();

        $inspect = $this->getJson($this->url("public/cancel-registration/{$rawToken}"));
        $inspect->assertOk()->assertJsonPath('data.state', 'expired');

        $response = $this->postJson($this->url("public/cancel-registration/{$rawToken}"));
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['token']);
    }

    public function test_reused_token_is_rejected(): void
    {
        $event = $this->createPublishedEvent();
        /** @var Registration $registration */
        [$ticketType, $registration] = $this->createFreeRegistration($event);

        $service = app(RegistrationCancellationService::class);
        $rawToken = $service->generateGuestCancellation($registration);

        // First cancellation succeeds
        $this->postJson($this->url("public/cancel-registration/{$rawToken}"))->assertOk();

        // Inspecting now shows already_cancelled
        $inspect = $this->getJson($this->url("public/cancel-registration/{$rawToken}"));
        $inspect->assertOk()->assertJsonPath('data.state', 'already_cancelled');

        // Second cancellation attempt is rejected with 409 Conflict
        $second = $this->postJson($this->url("public/cancel-registration/{$rawToken}"));
        $second->assertStatus(409);

        // Capacity was only decremented once
        $this->assertSame(0, $ticketType->fresh()->quantity_sold);
    }

    public function test_guest_cannot_cancel_a_paid_ticket(): void
    {
        $event = $this->createPublishedEvent();
        /** @var Registration $registration */
        [, , $registration] = $this->createPaidRegistration($event);

        // Even if a token were somehow forged for a paid ticket
        $rawToken = bin2hex(random_bytes(32));
        $registration->cancellation_token_hash = hash('sha256', $rawToken);
        $registration->saveQuietly();

        $inspect = $this->getJson($this->url("public/cancel-registration/{$rawToken}"));
        $inspect->assertOk()->assertJsonPath('data.state', 'forbidden');

        $response = $this->postJson($this->url("public/cancel-registration/{$rawToken}"));
        $response->assertForbidden();

        $this->assertSame(RegistrationStatus::Confirmed, $registration->fresh()->status);
    }

    // =========================================================================
    // 2. MEMBER TESTS
    // =========================================================================

    public function test_member_can_cancel_their_own_free_ticket(): void
    {
        $member = $this->emsUser(EmsRoles::ATTENDEE);
        $event = $this->createPublishedEvent();
        /** @var Registration $registration */
        [$ticketType, $registration] = $this->createFreeRegistration($event, $member);

        $response = $this->actingAsEms($member)
            ->postJson($this->url("public/registrations/{$registration->uuid}/cancel"));

        $response->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertSame(RegistrationStatus::Cancelled, $registration->fresh()->status);
        $this->assertSame('member', $registration->fresh()->cancellation_initiator_type);
        $this->assertSame($member->id, $registration->fresh()->cancelled_by_id);
        $this->assertSame(0, $ticketType->fresh()->quantity_sold);

        $this->assertDatabaseHas('ems_cancellation_audits', [
            'registration_id' => $registration->id,
            'initiator_type' => 'member',
            'user_id' => $member->id,
        ]);
    }

    public function test_member_cannot_cancel_another_members_ticket(): void
    {
        $member1 = $this->emsUser(EmsRoles::ATTENDEE, ['email' => 'member1@example.com']);
        $member2 = $this->emsUser(EmsRoles::ATTENDEE, ['email' => 'member2@example.com']);
        $event = $this->createPublishedEvent();
        /** @var Registration $registration */
        [, $registration] = $this->createFreeRegistration($event, $member1);

        $response = $this->actingAsEms($member2)
            ->postJson($this->url("public/registrations/{$registration->uuid}/cancel"));

        $response->assertNotFound();
        $this->assertSame(RegistrationStatus::Confirmed, $registration->fresh()->status);
    }

    public function test_member_cannot_cancel_a_paid_ticket(): void
    {
        $member = $this->emsUser(EmsRoles::ATTENDEE);
        $event = $this->createPublishedEvent();
        /** @var Registration $registration */
        [, , $registration] = $this->createPaidRegistration($event, $member);

        $response = $this->actingAsEms($member)
            ->postJson($this->url("public/registrations/{$registration->uuid}/cancel"));

        $response->assertForbidden();
        $this->assertSame(RegistrationStatus::Confirmed, $registration->fresh()->status);
    }

    public function test_cancelled_registrations_cannot_be_used_for_check_in(): void
    {
        $member = $this->emsUser(EmsRoles::ATTENDEE);
        $admin = $this->emsUser(EmsRoles::EVENT_ADMINISTRATOR);
        $event = $this->createPublishedEvent();
        /** @var Registration $registration */
        [, $registration] = $this->createFreeRegistration($event, $member);
        $ticket = $registration->tickets()->first();

        // Cancel the registration
        $this->actingAsEms($member)
            ->postJson($this->url("public/registrations/{$registration->uuid}/cancel"))
            ->assertOk();

        // Attempt manual check in via staff
        $checkInResponse = $this->actingAsEms($admin)
            ->postJson($this->url("events/{$event->uuid}/check-in"), [
                'ticket_code' => $ticket->code,
            ]);

        $checkInResponse->assertStatus(422);

        // Attempt by registration uuid
        $manualResponse = $this->actingAsEms($admin)
            ->postJson($this->url("events/{$event->uuid}/manual-check-in"), [
                'registration_uuid' => $registration->uuid,
            ]);

        $manualResponse->assertStatus(409);
    }

    // =========================================================================
    // 3. ADMIN TESTS
    // =========================================================================

    public function test_authorized_admin_can_cancel_free_tickets_with_reason(): void
    {
        $admin = $this->emsUser(EmsRoles::EVENT_ADMINISTRATOR);
        $event = $this->createPublishedEvent();
        /** @var Registration $registration */
        [$ticketType, $registration] = $this->createFreeRegistration($event);

        $response = $this->actingAsEms($admin)
            ->postJson($this->url("registrations/{$registration->uuid}/cancel"), [
                'reason' => 'Attendee requested cancellation via email.',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.registration.status', 'cancelled')
            ->assertJsonPath('data.audit.reason', 'Attendee requested cancellation via email.')
            ->assertJsonPath('data.audit.initiator_type', 'admin');

        $this->assertSame(RegistrationStatus::Cancelled, $registration->fresh()->status);
        $this->assertSame('Attendee requested cancellation via email.', $registration->fresh()->cancellation_reason);
        $this->assertSame(0, $ticketType->fresh()->quantity_sold);
    }

    public function test_admin_cancellation_requires_a_reason(): void
    {
        $admin = $this->emsUser(EmsRoles::EVENT_ADMINISTRATOR);
        $event = $this->createPublishedEvent();
        /** @var Registration $registration */
        [, $registration] = $this->createFreeRegistration($event);

        $response = $this->actingAsEms($admin)
            ->postJson($this->url("registrations/{$registration->uuid}/cancel"), [
                'reason' => '  ',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);

        $this->assertSame(RegistrationStatus::Confirmed, $registration->fresh()->status);
    }

    public function test_unauthorized_users_cannot_cancel_registrations(): void
    {
        $normalUser = $this->emsUser(EmsRoles::ATTENDEE);
        $event = $this->createPublishedEvent();
        /** @var Registration $registration */
        [, $registration] = $this->createFreeRegistration($event);

        $response = $this->actingAsEms($normalUser)
            ->postJson($this->url("registrations/{$registration->uuid}/cancel"), [
                'reason' => 'Illegal attempt',
            ]);

        // Gated by app.access:ems or policy
        $response->assertForbidden();
    }

    public function test_admin_can_cancel_paid_ticket_without_issuing_a_refund(): void
    {
        $admin = $this->emsUser(EmsRoles::EVENT_ADMINISTRATOR);
        $event = $this->createPublishedEvent();
        /** @var Registration $registration */
        /** @var Payment $payment */
        [$ticketType, , $registration, $payment] = $this->createPaidRegistration($event);

        $response = $this->actingAsEms($admin)
            ->postJson($this->url("registrations/{$registration->uuid}/cancel"), [
                'reason' => 'No-show cancellation as per non-refundable policy.',
                'issue_refund' => false,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.registration.status', 'cancelled')
            ->assertJsonPath('data.refund_status', 'not_requested');

        $regFresh = $registration->fresh();
        $this->assertSame(RegistrationStatus::Cancelled, $regFresh->status);
        $this->assertSame(RefundStatus::NotRequested, $regFresh->refund_status);
        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
        $this->assertSame(0, $ticketType->fresh()->quantity_sold);

        // Distinct registration status and refund status
        $this->assertDatabaseHas('ems_cancellation_audits', [
            'registration_id' => $registration->id,
            'refund_requested' => false,
            'refund_status' => 'not_requested',
        ]);
    }

    public function test_admin_can_cancel_paid_ticket_and_initiate_full_refund(): void
    {
        $admin = $this->emsUser(EmsRoles::EVENT_ADMINISTRATOR);
        $event = $this->createPublishedEvent();
        /** @var Registration $registration */
        /** @var Payment $payment */
        [$ticketType, , $registration, $payment] = $this->createPaidRegistration($event, null, 30.00);

        Http::fake([
            '*/v2/refunds' => Http::response([
                'refund' => [
                    'id' => 'sq_rfnd_12345',
                    'status' => 'COMPLETED',
                    'amount_money' => ['amount' => 3000, 'currency' => 'CAD'],
                    'payment_id' => $payment->provider_payment_id,
                ],
            ], 200),
        ]);

        $response = $this->actingAsEms($admin)
            ->postJson($this->url("registrations/{$registration->uuid}/cancel"), [
                'reason' => 'Event rescheduled, attendee requested refund.',
                'issue_refund' => true,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.registration.status', 'cancelled')
            ->assertJsonPath('data.refund_status', 'succeeded');

        $this->assertSame(RegistrationStatus::Cancelled, $registration->fresh()->status);
        $this->assertSame(RefundStatus::Succeeded, $registration->fresh()->refund_status);
        $this->assertSame(PaymentStatus::Refunded, $payment->fresh()->status);

        // Audit row has provider reference
        $this->assertDatabaseHas('ems_cancellation_audits', [
            'registration_id' => $registration->id,
            'refund_requested' => true,
            'refund_status' => 'succeeded',
            'provider_refund_id' => 'sq_rfnd_12345',
        ]);
    }

    public function test_refund_permission_is_enforced_independently(): void
    {
        // User with registrations.delete but WITHOUT payments.refund
        $limitedStaff = $this->emsUser(null);
        $role = Role::create(['name' => 'Event Manager', 'slug' => 'event-manager', 'uuid' => (string) Str::uuid()]);
        $limitedStaff->roles()->sync([$role->id]);

        $event = $this->createPublishedEvent();
        $event->staff()->create(['user_id' => $limitedStaff->id, 'role' => 'staff']);

        $deletePerm = \App\Models\Permission::firstOrCreate(
            ['slug' => EmsPermissions::REGISTRATIONS_DELETE],
            ['name' => 'EMS: Delete Registrations', 'group' => 'Registrations', 'module' => 'EMS']
        );
        $role->permissions()->sync([$deletePerm->id]);

        /** @var Registration $registration */
        [, , $registration] = $this->createPaidRegistration($event);

        // Can cancel without refund
        $okResponse = $this->actingAsEms($limitedStaff)
            ->postJson($this->url("registrations/{$registration->uuid}/cancel"), [
                'reason' => 'Cancel without refund is allowed.',
                'issue_refund' => false,
            ]);
        $okResponse->assertOk();

        // Create another paid registration
        [, , $registration2] = $this->createPaidRegistration($event);

        // Attempting to cancel WITH refund must be forbidden (403)
        $forbiddenResponse = $this->actingAsEms($limitedStaff)
            ->postJson($this->url("registrations/{$registration2->uuid}/cancel"), [
                'reason' => 'Attempting refund without permission.',
                'issue_refund' => true,
            ]);
        $forbiddenResponse->assertForbidden();

        $this->assertSame(RegistrationStatus::Confirmed, $registration2->fresh()->status);
    }

    // =========================================================================
    // 4. PAYMENT & REFUND RESILIENCE TESTS
    // =========================================================================

    public function test_failed_gateway_refund_does_not_falsely_indicate_success(): void
    {
        $admin = $this->emsUser(EmsRoles::EVENT_ADMINISTRATOR);
        $event = $this->createPublishedEvent();
        /** @var Registration $registration */
        [, , $registration, $payment] = $this->createPaidRegistration($event, null, 25.00);

        Http::fake([
            '*/v2/refunds' => Http::response([
                'errors' => [
                    ['category' => 'PAYMENT_METHOD_ERROR', 'code' => 'CARD_DECLINED', 'detail' => 'Card was declined.'],
                ],
            ], 400),
        ]);

        $response = $this->actingAsEms($admin)
            ->postJson($this->url("registrations/{$registration->uuid}/cancel"), [
                'reason' => 'Attendee requested cancellation.',
                'issue_refund' => true,
            ]);

        // Cancellation succeeded, but refund failed
        $response->assertOk()
            ->assertJsonPath('data.registration.status', 'cancelled')
            ->assertJsonPath('data.refund_status', 'failed');

        $this->assertSame(RegistrationStatus::Cancelled, $registration->fresh()->status);
        $this->assertSame(RefundStatus::Failed, $registration->fresh()->refund_status);

        // Payment status is NOT falsely updated to Refunded
        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);

        $this->assertDatabaseHas('ems_cancellation_audits', [
            'registration_id' => $registration->id,
            'refund_status' => 'failed',
        ]);
    }

    public function test_cancellation_history_endpoint_returns_audit_records(): void
    {
        $admin = $this->emsUser(EmsRoles::EVENT_ADMINISTRATOR);
        $event = $this->createPublishedEvent();
        /** @var Registration $registration */
        [$ticketType, $registration] = $this->createFreeRegistration($event);

        $this->actingAsEms($admin)
            ->postJson($this->url("registrations/{$registration->uuid}/cancel"), [
                'reason' => 'Medical emergency reported by attendee.',
            ])
            ->assertOk();

        // Event-level history
        $eventHistory = $this->actingAsEms($admin)
            ->getJson($this->url("events/{$event->uuid}/cancellation-history"));

        $eventHistory->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.registration_reference', $registration->reference)
            ->assertJsonPath('data.0.reason', 'Medical emergency reported by attendee.')
            ->assertJsonPath('data.0.initiator_type', 'admin');

        // Registration-level history
        $regHistory = $this->actingAsEms($admin)
            ->getJson($this->url("registrations/{$registration->uuid}/cancellation-history"));

        $regHistory->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.reason', 'Medical emergency reported by attendee.');
    }
}
