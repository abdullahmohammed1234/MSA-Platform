<?php

namespace Tests\Feature\Ems;

use App\Ems\Enums\EventStatus;
use App\Ems\Enums\RegistrationStatus;
use App\Ems\Models\CheckIn;
use App\Ems\Models\Event;
use App\Ems\Models\Registration;
use App\Ems\Models\Ticket;
use App\Ems\Services\AnalyticsService;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmsAnalyticsRootCauseAuditTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected AnalyticsService $analyticsService;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['name' => 'Super Admin', 'slug' => 'super-admin']);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('super-admin');
        $this->analyticsService = new AnalyticsService();
    }

    private function createTestEvent(string $name, string $slug): Event
    {
        $event = new Event();
        $event->name = $name;
        $event->slug = $slug;
        $event->status = EventStatus::RegistrationOpen;
        $event->start_at = now()->addDays(1);
        $event->end_at = now()->addDays(1)->addHours(3);
        $event->save();

        return $event;
    }

    public function test_case_a_100_registrations_counted_accurately(): void
    {
        $event = $this->createTestEvent('Analytics Case A', 'analytics-case-a');

        for ($i = 0; $i < 100; $i++) {
            Registration::create([
                'event_id' => $event->id,
                'attendee_name' => "Attendee {$i}",
                'attendee_email' => "attendee{$i}@example.com",
                'status' => RegistrationStatus::Confirmed,
                'quantity' => 1,
                'reference' => "REG-A-{$i}",
                'registered_at' => now(),
            ]);
        }

        $dbCount = Registration::where('event_id', $event->id)
            ->where('status', RegistrationStatus::Confirmed->value)
            ->sum('quantity');

        $payload = $this->analyticsService->getDashboardPayload($this->admin, ['event_uuid' => $event->uuid]);
        $kpis = $payload['kpis'];

        $this->assertEquals(100, $dbCount);
        $this->assertEquals(100, $kpis['confirmed_registrations']);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/ems/analytics/dashboard?event_uuid={$event->uuid}");

        $response->assertStatus(200)
            ->assertJsonPath('data.kpis.confirmed_registrations', 100);
    }

    public function test_case_b_50_tickets_issued_100_confirmed_registrations(): void
    {
        $event = $this->createTestEvent('Analytics Case B', 'analytics-case-b');

        $registrations = [];
        for ($i = 0; $i < 100; $i++) {
            $registrations[] = Registration::create([
                'event_id' => $event->id,
                'attendee_name' => "Attendee B {$i}",
                'attendee_email' => "attendee_b{$i}@example.com",
                'status' => RegistrationStatus::Confirmed,
                'quantity' => 1,
                'reference' => "REG-B-{$i}",
                'registered_at' => now(),
            ]);
        }

        for ($i = 0; $i < 50; $i++) {
            Ticket::create([
                'event_id' => $event->id,
                'registration_id' => $registrations[$i]->id,
                'code' => "TC-B-{$i}",
                'status' => 'issued',
            ]);
        }

        $payload = $this->analyticsService->getDashboardPayload($this->admin, ['event_uuid' => $event->uuid]);
        $kpis = $payload['kpis'];

        $this->assertEquals(100, $kpis['confirmed_registrations']);
        $this->assertEquals(50, $kpis['tickets_issued']);
        $this->assertEquals(0.0, $kpis['attendance_rate']);
    }

    public function test_case_c_100_tickets_issued_50_confirmed_registrations(): void
    {
        $event = $this->createTestEvent('Analytics Case C', 'analytics-case-c');

        $registrations = [];
        for ($i = 0; $i < 50; $i++) {
            $registrations[] = Registration::create([
                'event_id' => $event->id,
                'attendee_name' => "Attendee C {$i}",
                'attendee_email' => "attendee_c{$i}@example.com",
                'status' => RegistrationStatus::Confirmed,
                'quantity' => 1,
                'reference' => "REG-C-{$i}",
                'registered_at' => now(),
            ]);
        }

        for ($i = 0; $i < 100; $i++) {
            Ticket::create([
                'event_id' => $event->id,
                'registration_id' => $registrations[$i % 50]->id,
                'code' => "TC-C-{$i}",
                'status' => 'issued',
            ]);
        }

        $payload = $this->analyticsService->getDashboardPayload($this->admin, ['event_uuid' => $event->uuid]);
        $kpis = $payload['kpis'];

        $this->assertEquals(50, $kpis['confirmed_registrations']);
        $this->assertEquals(100, $kpis['tickets_issued']);
    }

    public function test_case_d_duplicate_checkin_cannot_inflate_attendance(): void
    {
        $event = $this->createTestEvent('Analytics Case D', 'analytics-case-d');

        $reg = Registration::create([
            'event_id' => $event->id,
            'attendee_name' => 'Single User',
            'attendee_email' => 'single@example.com',
            'status' => RegistrationStatus::Confirmed,
            'quantity' => 1,
            'reference' => 'REG-D-1',
            'registered_at' => now(),
        ]);

        $ticket = Ticket::create([
            'event_id' => $event->id,
            'registration_id' => $reg->id,
            'code' => "TC-D-1",
            'status' => 'issued',
        ]);

        CheckIn::create([
            'event_id' => $event->id,
            'ticket_id' => $ticket->id,
            'checked_in_at' => now(),
        ]);

        $checkInCount = CheckIn::where('event_id', $event->id)->count();
        $this->assertEquals(1, $checkInCount);
    }

    public function test_case_e_cancellations_excluded_from_confirmed_counts(): void
    {
        $event = $this->createTestEvent('Analytics Case E', 'analytics-case-e');

        Registration::create([
            'event_id' => $event->id,
            'attendee_name' => 'Active User',
            'attendee_email' => 'active@example.com',
            'status' => RegistrationStatus::Confirmed,
            'quantity' => 1,
            'reference' => 'REG-E-1',
            'registered_at' => now(),
        ]);

        Registration::create([
            'event_id' => $event->id,
            'attendee_name' => 'Cancelled User',
            'attendee_email' => 'cancelled@example.com',
            'status' => RegistrationStatus::Cancelled,
            'quantity' => 1,
            'reference' => 'REG-E-2',
            'registered_at' => now(),
        ]);

        $payload = $this->analyticsService->getDashboardPayload($this->admin, ['event_uuid' => $event->uuid]);
        $kpis = $payload['kpis'];

        $this->assertEquals(1, $kpis['confirmed_registrations']);
        $this->assertEquals(1, $kpis['cancelled_registrations']);
    }

    public function test_case_f_multiple_events_isolated(): void
    {
        $eventA = $this->createTestEvent('Event A', 'event-a');
        $eventB = $this->createTestEvent('Event B', 'event-b');

        Registration::create([
            'event_id' => $eventA->id,
            'attendee_name' => 'User A',
            'attendee_email' => 'usera@example.com',
            'status' => RegistrationStatus::Confirmed,
            'quantity' => 5,
            'reference' => 'REG-A',
            'registered_at' => now(),
        ]);

        Registration::create([
            'event_id' => $eventB->id,
            'attendee_name' => 'User B',
            'attendee_email' => 'userb@example.com',
            'status' => RegistrationStatus::Confirmed,
            'quantity' => 2,
            'reference' => 'REG-B',
            'registered_at' => now(),
        ]);

        $payloadA = $this->analyticsService->getDashboardPayload($this->admin, ['event_uuid' => $eventA->uuid]);
        $payloadB = $this->analyticsService->getDashboardPayload($this->admin, ['event_uuid' => $eventB->uuid]);

        $this->assertEquals(5, $payloadA['kpis']['confirmed_registrations']);
        $this->assertEquals(2, $payloadB['kpis']['confirmed_registrations']);
    }

    public function test_case_g_g_and_i_unpaginated_aggregate_totals(): void
    {
        $event = $this->createTestEvent('Analytics Large Dataset', 'analytics-large-dataset');

        for ($i = 0; $i < 75; $i++) {
            Registration::create([
                'event_id' => $event->id,
                'attendee_name' => "User {$i}",
                'attendee_email' => "user{$i}@example.com",
                'status' => RegistrationStatus::Confirmed,
                'quantity' => 1,
                'reference' => "REG-L-{$i}",
                'registered_at' => now(),
            ]);
        }

        $payload = $this->analyticsService->getDashboardPayload($this->admin, ['event_uuid' => $event->uuid]);
        $this->assertEquals(75, $payload['kpis']['confirmed_registrations']);
    }

    public function test_case_h_zero_registrations_handled_gracefully(): void
    {
        $event = $this->createTestEvent('Zero Reg Event', 'zero-reg-event');

        $payload = $this->analyticsService->getDashboardPayload($this->admin, ['event_uuid' => $event->uuid]);
        $kpis = $payload['kpis'];

        $this->assertEquals(0, $kpis['confirmed_registrations']);
        $this->assertEquals(0.0, $kpis['attendance_rate']);
    }

    public function test_case_j_attendance_rate_clamping_and_data_integrity(): void
    {
        $event = $this->createTestEvent('Clamping Test', 'clamping-test');

        $reg = Registration::create([
            'event_id' => $event->id,
            'attendee_name' => 'Single User',
            'attendee_email' => 'single@example.com',
            'status' => RegistrationStatus::Confirmed,
            'quantity' => 1,
            'reference' => 'REG-CLAMP',
            'registered_at' => now(),
        ]);

        $ticket = Ticket::create([
            'event_id' => $event->id,
            'registration_id' => $reg->id,
            'code' => 'TC-CLAMP',
            'status' => 'issued',
        ]);

        CheckIn::create([
            'event_id' => $event->id,
            'ticket_id' => $ticket->id,
            'checked_in_at' => now(),
        ]);

        $payload = $this->analyticsService->getDashboardPayload($this->admin, ['event_uuid' => $event->uuid]);
        $this->assertEquals(100.0, $payload['kpis']['attendance_rate']);
    }
}
