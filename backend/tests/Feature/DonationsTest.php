<?php

namespace Tests\Feature;

use App\Donations\Enums\DonationStatus;
use App\Donations\Models\Donation;
use App\Ems\Models\WebhookEvent;
use App\Ems\Services\SquareWebhookService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DonationsTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles & permissions
        $this->seed();

        $this->adminUser = User::factory()->create(['email' => 'admin@sfumsa.ca']);
        $this->adminUser->assignRole('super-admin');

        $this->regularUser = User::factory()->create(['email' => 'student@sfu.ca']);
    }

    public function test_public_checkout_validates_amounts_and_creates_pending_donation(): void
    {
        // 1. Invalid amount below $1.00 CAD
        $response = $this->postJson('/api/v1/donations/checkout', [
            'donor_name' => 'Fatima Ahmed',
            'donor_email' => 'fatima@sfu.ca',
            'amount_cents' => 50,
        ]);
        $response->assertStatus(422);

        // 2. Valid amount ($25.00 CAD = 2500 cents)
        $response = $this->postJson('/api/v1/donations/checkout', [
            'donor_name' => 'Fatima Ahmed',
            'donor_email' => 'fatima@sfu.ca',
            'amount_cents' => 2500,
            'is_anonymous' => true,
            'dedication' => 'For Friday Jumu\'ah program',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('donation.amount_cents', 2500)
            ->assertJsonPath('donation.status', 'pending');

        $this->assertDatabaseHas('donations', [
            'donor_name' => 'Fatima Ahmed',
            'donor_email' => 'fatima@sfu.ca',
            'amount_cents' => 2500,
            'is_anonymous' => 1,
            'status' => 'pending',
        ]);
    }

    public function test_public_status_endpoint_returns_donation_details_without_modifying_status(): void
    {
        $donation = Donation::create([
            'donor_name' => 'Bilal Khan',
            'donor_email' => 'bilal@sfu.ca',
            'amount_cents' => 5000,
            'status' => DonationStatus::Pending,
        ]);

        $response = $this->getJson("/api/v1/donations/{$donation->uuid}/status");

        $response->assertOk()
            ->assertJsonPath('donation.uuid', $donation->uuid)
            ->assertJsonPath('donation.status', 'pending')
            ->assertJsonPath('donation.amount_cents', 5000);

        // Assert status remains pending
        $this->assertSame('pending', $donation->fresh()->status->value);
    }

    public function test_webhook_marks_donation_as_paid_and_is_idempotent(): void
    {
        $donation = Donation::create([
            'donor_name' => 'Tariq Ziad',
            'donor_email' => 'tariq@sfu.ca',
            'amount_cents' => 10000,
            'status' => DonationStatus::Pending,
            'square_order_id' => 'sq_ord_don_123',
        ]);

        $webhookService = app(SquareWebhookService::class);

        $payload = [
            'event_id' => 'evt_don_paid_1',
            'type' => 'payment.updated',
            'data' => [
                'object' => [
                    'payment' => [
                        'id' => 'sq_pay_don_123',
                        'order_id' => 'sq_ord_don_123',
                        'status' => 'COMPLETED',
                        'reference_id' => $donation->donation_number,
                    ],
                ],
            ],
        ];

        // Process webhook first time
        $record = WebhookEvent::create([
            'provider' => 'square',
            'event_id' => 'evt_don_paid_1',
            'event_type' => 'payment.updated',
            'status' => 'received',
            'payload' => $payload,
        ]);

        $webhookService->processRecord($record);

        $donation->refresh();
        $this->assertSame(DonationStatus::Paid, $donation->status);
        $this->assertSame('sq_pay_don_123', $donation->square_payment_id);
        $this->assertNotNull($donation->paid_at);

        // Duplicate webhook process
        $recordDuplicate = WebhookEvent::create([
            'provider' => 'square',
            'event_id' => 'evt_don_paid_1_dup',
            'event_type' => 'payment.updated',
            'status' => 'received',
            'payload' => $payload,
        ]);

        $webhookService->processRecord($recordDuplicate);

        $donation->refresh();
        $this->assertSame(DonationStatus::Paid, $donation->status);
    }

    public function test_dms_admin_endpoints_enforce_authorization(): void
    {
        // Unauthenticated access fails
        $this->getJson('/api/v1/donations/admin/dashboard')->assertStatus(401);

        // Regular user without permissions fails
        $this->actingAs($this->regularUser)
            ->getJson('/api/v1/donations/admin/dashboard')
            ->assertStatus(403);

        // Super Admin succeeds
        $this->actingAs($this->adminUser)
            ->getJson('/api/v1/donations/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_dms_admin_donations_list_and_csv_export(): void
    {
        Donation::create([
            'donor_name' => 'Yusuf Ali',
            'donor_email' => 'yusuf@sfu.ca',
            'amount_cents' => 7500,
            'status' => DonationStatus::Paid,
            'paid_at' => now(),
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/donations/admin/donations');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'donations');

        // CSV Export
        $exportResponse = $this->actingAs($this->adminUser)
            ->get('/api/v1/donations/admin/export');

        $exportResponse->assertOk();
        $this->assertStringContainsString('text/csv', $exportResponse->headers->get('Content-Type'));
        $this->assertStringContainsString('Yusuf Ali', $exportResponse->streamedContent());
    }

    public function test_systems_control_plane_donations_endpoints(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/admin/systems/donations');

        $response->assertOk()
            ->assertJsonPath('system.slug', 'donations')
            ->assertJsonPath('system.name', 'Donation Management System');

        $healthResponse = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/admin/systems/donations/health');

        $healthResponse->assertOk()
            ->assertJsonPath('success', true);

        $metricsResponse = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/admin/systems/donations/metrics');

        $metricsResponse->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_anonymous_donor_privacy_protection_on_public_status(): void
    {
        $donation = Donation::create([
            'donor_name' => 'Secret Donor',
            'donor_email' => 'secret@sfu.ca',
            'amount_cents' => 5000,
            'status' => DonationStatus::Paid,
            'is_anonymous' => true,
        ]);

        $response = $this->getJson("/api/v1/donations/{$donation->uuid}/status");

        $response->assertOk()
            ->assertJsonPath('donation.is_anonymous', true)
            ->assertJsonPath('donation.donor_name', 'Anonymous')
            ->assertJsonPath('donation.donor_email', '***@***.***');
    }

    public function test_csv_formula_injection_escaping(): void
    {
        Donation::create([
            'donor_name' => '=SUM(1,2)',
            'donor_email' => 'malicious@sfu.ca',
            'amount_cents' => 1000,
            'status' => DonationStatus::Paid,
            'dedication' => '@cmd|\' /C calc\'!A1',
            'is_anonymous' => false,
        ]);

        $exportResponse = $this->actingAs($this->adminUser)
            ->get('/api/v1/donations/admin/export');

        $exportResponse->assertOk();
        $content = $exportResponse->streamedContent();

        $this->assertStringContainsString('\'=SUM(1,2)', $content);
        $this->assertStringContainsString('\'@cmd', $content);
    }

    public function test_double_refund_prevention_and_idempotency(): void
    {
        $donation = Donation::create([
            'donor_name' => 'Sara Khan',
            'donor_email' => 'sara@sfu.ca',
            'amount_cents' => 3000,
            'status' => DonationStatus::Paid,
        ]);

        $paymentService = app(\App\Donations\Services\DonationPaymentService::class);

        $refund1 = $paymentService->refundDonation($donation, 'First refund request', $this->adminUser);
        $this->assertSame('completed', $refund1->status);
        $this->assertSame(DonationStatus::Refunded, $donation->fresh()->status);

        // Second refund attempt should return the exact existing refund
        $refund2 = $paymentService->refundDonation($donation->fresh(), 'Duplicate refund attempt', $this->adminUser);
        $this->assertSame($refund1->id, $refund2->id);
    }

    public function test_invalid_webhook_signature_rejection(): void
    {
        $response = $this->postJson('/api/v1/webhooks/square', [
            'event_id' => 'evt_fake_123',
            'type' => 'payment.updated',
        ], [
            'X-Square-Hmacsha256-Signature' => 'invalid_signature_string',
        ]);

        $response->assertStatus(401);
    }
}
