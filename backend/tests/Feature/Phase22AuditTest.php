<?php

namespace Tests\Feature;

use App\Ems\Models\Event;
use App\Ems\Models\EventFeedback;
use App\Ems\Models\EventVolunteer;
use App\Ems\Models\Order;
use App\Ems\Models\Payment;
use App\Ems\Models\Registration;
use App\Ems\Models\TicketType;
use App\Ems\Enums\PaymentStatus;
use App\Ems\Enums\RegistrationStatus;
use App\Ems\Services\Notifications\TemplateRenderer;
use App\Ems\Support\EmsRoles;
use App\Mlibms\Services\BookMetadataService;
use App\Models\User;
use Tests\Feature\Ems\EmsTestCase;

class Phase22AuditTest extends EmsTestCase
{
    /**
     * 1. MLibMS ISBN Normalization and Validation Audit
     */
    public function test_mlibms_isbn_normalization_and_validation(): void
    {
        $service = new BookMetadataService();

        // Normalization
        $this->assertEquals('9780132350884', $service->normalizeIsbn(' 978-0-13-235088-4 '));
        $this->assertEquals('0132350882', $service->normalizeIsbn('0 13 235088 2'));
        $this->assertEquals('080442957X', $service->normalizeIsbn('0-8044-2957-x'));

        // Validation
        $this->assertTrue($service->isValidIsbn('9780132350884'));
        $this->assertTrue($service->isValidIsbn('0132350882'));
        $this->assertTrue($service->isValidIsbn('080442957X'));
        $this->assertFalse($service->isValidIsbn('9780132350880')); // Wrong checksum
        $this->assertFalse($service->isValidIsbn('12345')); // Invalid length

        // ISBN-10 to ISBN-13 Conversion
        $this->assertEquals('9780132350884', $service->convertIsbn10To13('0132350882'));
    }

    /**
     * 2. EMS Payment Override Security & IDOR Authorization Audit
     */
    public function test_payment_override_authorization_and_duplicate_protection(): void
    {
        $guestUser = User::factory()->create(['email' => 'guest@example.com']);
        $superAdmin = $this->emsUser(EmsRoles::SUPER_ADMIN);

        $event = Event::factory()->create();
        $ticketType = TicketType::factory()->create(['event_id' => $event->id, 'quantity' => 10]);
        $registration = Registration::factory()->create([
            'event_id' => $event->id,
            'ticket_type_id' => $ticketType->id,
            'status' => RegistrationStatus::AwaitingPayment->value,
            'attendee_email' => 'attendee@example.com',
        ]);

        // 1. Guest / Unauthorized user attempt must be rejected (403)
        $unauthResp = $this->actingAs($guestUser)
            ->postJson('/api/v1/ems/admin/payments/override', [
                'registration_id' => $registration->id,
                'payment_method' => 'cash',
                'reason' => 'Unauthorized override test',
            ]);
        $unauthResp->assertStatus(403);

        // 2. Super-admin override succeeds
        $overrideResp = $this->actingAsEms($superAdmin)
            ->postJson('/api/v1/ems/admin/payments/override', [
                'registration_id' => $registration->id,
                'payment_method' => 'cash',
                'reference_id' => 'CASH-REF-99001',
                'reason' => 'Collected cash at venue door.',
            ]);
        $overrideResp->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertEquals(RegistrationStatus::Confirmed->value, $registration->fresh()->status->value);
        $this->assertCount(1, $registration->fresh()->tickets);

        // 3. Duplicate override on already confirmed & ticketed registration returns 409 Conflict
        $dupResp = $this->actingAsEms($superAdmin)
            ->postJson('/api/v1/ems/admin/payments/override', [
                'registration_id' => $registration->id,
                'payment_method' => 'cash',
                'reason' => 'Attempting duplicate override',
            ]);
        $dupResp->assertStatus(409);
    }

    /**
     * 3. External Square Reconciliation Audit & Audit Trail Logging
     */
    public function test_external_square_reconciliation_and_audit_logging(): void
    {
        $superAdmin = $this->emsUser(EmsRoles::SUPER_ADMIN);
        $event = Event::factory()->create();
        $ticketType = TicketType::factory()->create(['event_id' => $event->id]);
        $registration = Registration::factory()->create([
            'event_id' => $event->id,
            'ticket_type_id' => $ticketType->id,
            'status' => RegistrationStatus::AwaitingPayment->value,
        ]);

        $txnId = 'SQ_POS_TXN_AUDIT_7722';

        $response = $this->actingAsEms($superAdmin)
            ->postJson('/api/v1/ems/admin/payments/reconcile-external', [
                'registration_id' => $registration->id,
                'external_transaction_id' => $txnId,
                'payment_method' => 'square_pos',
                'reason' => 'Reconciling POS Terminal receipt #7722',
            ]);

        $response->assertStatus(200);

        // Check duplicate transaction protection
        $dupSquare = $this->actingAsEms($superAdmin)
            ->postJson('/api/v1/ems/admin/payments/reconcile-external', [
                'registration_id' => $registration->id,
                'external_transaction_id' => $txnId,
                'reason' => 'Duplicate POS transaction submit',
            ]);
        $dupSquare->assertStatus(409);
    }

    /**
     * 4. EMS Feedback Workflow & Privacy Audit
     */
    public function test_ems_feedback_public_endpoint_and_url_security(): void
    {
        $event = Event::factory()->create(['slug' => 'test-feedback-event']);
        $registration = Registration::factory()->create([
            'event_id' => $event->id,
            'status' => RegistrationStatus::Confirmed->value,
            'attendee_email' => 'feedback.user@example.com',
        ]);

        // Feedback URL rendering check
        $renderer = app(TemplateRenderer::class);
        $rendered = $renderer->render('feedback_request', [
            'attendee_name' => 'Feedback User',
            'event' => $event,
        ]);
        $feedbackUrl = $rendered['body_html'];

        $this->assertStringContainsString("/events/{$event->slug}/feedback", $feedbackUrl);
        $this->assertStringNotContainsString('feedback.user@example.com', $feedbackUrl); // Email not in URL

        // Submit feedback publicly
        $submitResp = $this->postJson("/api/v1/ems/public/events/{$event->slug}/feedback", [
            'overall_rating' => 5,
            'organization_rating' => 5,
            'program_rating' => 4,
            'venue_rating' => 5,
            'text_feedback' => 'Wonderful event experience!',
            'email' => 'feedback.user@example.com',
        ]);

        $submitResp->assertStatus(201)
            ->assertJsonPath('success', true);

        // Duplicate submission prevention
        $dupFeedback = $this->postJson("/api/v1/ems/public/events/{$event->slug}/feedback", [
            'overall_rating' => 4,
            'organization_rating' => 4,
            'program_rating' => 4,
            'venue_rating' => 4,
            'email' => 'feedback.user@example.com',
        ]);

        $dupFeedback->assertStatus(409);
    }

    /**
     * 5. EMS Volunteer Management & CSV Export Security Audit
     */
    public function test_ems_volunteer_csv_export_formatting_and_escaping(): void
    {
        $admin = $this->emsUser(EmsRoles::SUPER_ADMIN);
        $event = Event::factory()->create();

        // Create volunteer with special characters (commas, quotes, newlines, Unicode)
        EventVolunteer::create([
            'event_id' => $event->id,
            'name' => 'Tariq Al-Mansoor, Esq.',
            'email' => 'tariq@example.com',
            'phone' => '604-555-0188',
            'interests' => ['Registration', 'Media & AV'],
            'availability' => 'Full Day',
            'experience' => 'Managed "VIP" check-in desk at annual conference.',
            'notes' => "Line 1: Special request\nLine 2: Prefer morning shift",
            'status' => 'pending',
        ]);

        $response = $this->actingAsEms($admin)
            ->get("/api/v1/ems/events/{$event->id}/volunteers/export");

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $csvContent = $response->streamedContent();

        // Verify CSV contains properly escaped fields per RFC 4180
        $this->assertStringContainsString('"Tariq Al-Mansoor, Esq."', $csvContent);
        $this->assertStringContainsString('Managed ""VIP"" check-in desk', $csvContent);
        $this->assertStringContainsString('tariq@example.com', $csvContent);
    }
}
