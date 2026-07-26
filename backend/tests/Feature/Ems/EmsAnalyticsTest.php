<?php

namespace Tests\Feature\Ems;

use App\Ems\Models\Event;
use App\Ems\Models\Registration;
use App\Ems\Models\Ticket;
use App\Ems\Models\CheckIn;
use App\Ems\Models\Payment;
use App\Ems\Enums\EventStatus;
use App\Ems\Enums\PaymentStatus;
use App\Ems\Enums\RegistrationStatus;
use App\Ems\Support\EmsRoles;
use App\Ems\Support\EmsPermissions;
use App\Models\AnalyticsReport;
use App\Ems\Jobs\GenerateReportJob;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EmsAnalyticsTest extends EmsTestCase
{
    public function test_general_dashboard_analytics_kpi_calculations(): void
    {
        $administrator = $this->emsUser(EmsRoles::EVENT_ADMINISTRATOR);
        $event = Event::factory()->status(EventStatus::Live)->create(['capacity' => 100]);

        // Add 2 confirmed registrations
        $reg1 = Registration::factory()->create([
            'event_id' => $event->id,
            'status' => RegistrationStatus::Confirmed->value,
            'type' => 'paid',
            'quantity' => 2,
        ]);
        $reg2 = Registration::factory()->create([
            'event_id' => $event->id,
            'status' => RegistrationStatus::Confirmed->value,
            'type' => 'free',
            'quantity' => 1,
        ]);

        // Add 1 settled payment
        Payment::create([
            'uuid' => (string) Str::uuid(),
            'registration_id' => $reg1->id,
            'amount' => 50.00,
            'amount_refunded' => 10.00,
            'status' => PaymentStatus::Paid->value,
            'provider' => 'stripe',
        ]);

        // Issue 3 tickets
        $ticket1 = Ticket::factory()->create(['event_id' => $event->id, 'registration_id' => $reg1->id, 'status' => 'issued']);
        $ticket2 = Ticket::factory()->create(['event_id' => $event->id, 'registration_id' => $reg1->id, 'status' => 'issued']);
        $ticket3 = Ticket::factory()->create(['event_id' => $event->id, 'registration_id' => $reg2->id, 'status' => 'issued']);

        // Check in 2 tickets
        CheckIn::create([
            'uuid' => (string) Str::uuid(),
            'event_id' => $event->id,
            'ticket_id' => $ticket1->id,
            'registration_id' => $reg1->id,
            'checked_in_at' => now(),
        ]);
        CheckIn::create([
            'uuid' => (string) Str::uuid(),
            'event_id' => $event->id,
            'ticket_id' => $ticket3->id,
            'registration_id' => $reg2->id,
            'checked_in_at' => now(),
        ]);

        // Fetch general dashboard
        $response = $this->actingAsEms($administrator)->getJson($this->url('analytics/dashboard'));

        $response->assertOk();
        $this->assertSuccessEnvelope($response);

        $response->assertJsonStructure([
            'data' => [
                'kpis' => [
                    'total_registrations',
                    'confirmed_registrations',
                    'checked_in',
                    'no_shows',
                    'attendance_rate',
                    'no_show_rate',
                    'gross_revenue',
                    'refunds',
                    'net_revenue',
                    'capacity_utilization',
                ],
                'charts' => [
                    'registrations_over_time',
                    'member_breakdown',
                    'ticket_performance',
                    'early_bird',
                    'no_shows',
                ]
            ],
        ]);

        // Total Registrations = reg1.qty(2) + reg2.qty(1) = 3
        $response->assertJsonPath('data.kpis.total_registrations', 3);
        $response->assertJsonPath('data.kpis.checked_in', 2);
        // Expected = 3 issued tickets. 2 checked in. 1 no-show.
        $response->assertJsonPath('data.kpis.no_shows', 1);
        // Attendance rate = 2 / 3 * 100 = 66.7%
        $response->assertJsonPath('data.kpis.attendance_rate', 66.7);
        $response->assertJsonPath('data.kpis.no_show_rate', 33.3);

        // Revenue = 50.00, refund = 10.00, net = 40.00
        $response->assertJsonPath('data.kpis.gross_revenue', 50);
        $response->assertJsonPath('data.kpis.refunds', 10);
        $response->assertJsonPath('data.kpis.net_revenue', 40);
        
        // Capacity utilization = 3 / 100 * 100 = 3%
        $response->assertJsonPath('data.kpis.capacity_utilization', 3);
    }

    public function test_event_staff_is_forbidden_from_revenue_analytics(): void
    {
        $staff = $this->emsUser(EmsRoles::EVENT_STAFF);
        $event = Event::factory()->status(EventStatus::Live)->create();

        // Put staff on event team so they can see the event itself
        $event->staff()->create(['user_id' => $staff->id, 'role' => 'staff']);

        // Staff attempts to read revenue analytics
        $response = $this->actingAsEms($staff)->getJson($this->url("events/{$event->uuid}/revenue"));
        $response->assertForbidden();
    }

    public function test_event_organizer_can_access_revenue_analytics_on_their_events(): void
    {
        $organizer = $this->emsUser(EmsRoles::EVENT_ORGANIZER);
        $event = Event::factory()->status(EventStatus::Live)->organizedBy($organizer)->create();

        $response = $this->actingAsEms($organizer)->getJson($this->url("events/{$event->uuid}/revenue"));
        $response->assertOk();
        $this->assertSuccessEnvelope($response);
    }

    public function test_organizer_cannot_access_analytics_for_unauthorized_events(): void
    {
        $organizer1 = $this->emsUser(EmsRoles::EVENT_ORGANIZER);
        $organizer2 = $this->emsUser(EmsRoles::EVENT_ORGANIZER);

        // Event owned by organizer2
        $event = Event::factory()->status(EventStatus::Live)->organizedBy($organizer2)->create();

        // organizer1 attempts to read event specific analytics
        $response = $this->actingAsEms($organizer1)->getJson($this->url("events/{$event->uuid}/analytics"));
        $response->assertForbidden();
    }

    public function test_event_report_export_is_queued_and_processed(): void
    {
        Queue::fake();

        $administrator = $this->emsUser(EmsRoles::EVENT_ADMINISTRATOR);
        $event = Event::factory()->status(EventStatus::Live)->create();

        $response = $this->actingAsEms($administrator)->postJson($this->url("events/{$event->uuid}/reports/export"), [
            'title' => 'Audit Summary',
            'format' => 'pdf',
            'sections' => [
                'registrations' => true,
                'revenue' => false,
                'attendance' => true,
                'ticket_sales' => false,
                'payments' => false,
                'waitlist' => false,
                'check_ins' => false,
            ],
        ]);

        $response->assertOk();
        $this->assertSuccessEnvelope($response);
        $reportUuid = $response->json('data.uuid');

        $this->assertDatabaseHas('analytics_reports', [
            'uuid' => $reportUuid,
            'title' => 'Audit Summary',
            'type' => 'ems',
        ]);

        Queue::assertPushed(GenerateReportJob::class);
    }

    public function test_processed_report_can_be_downloaded(): void
    {
        Storage::fake('local');
        $administrator = $this->emsUser(EmsRoles::EVENT_ADMINISTRATOR);

        // Create a report file in mock storage
        $uuid = (string) Str::uuid();
        $filePath = "reports/{$uuid}.pdf";
        Storage::disk('local')->put($filePath, 'PDF mock content');

        $report = AnalyticsReport::create([
            'uuid' => $uuid,
            'title' => 'Monthly Breakdown',
            'type' => 'ems',
            'generated_by' => $administrator->id,
            'file_path' => $filePath,
        ]);

        $response = $this->actingAsEms($administrator)->getJson($this->url("reports/{$report->uuid}/download"));
        $response->assertOk();
        $this->assertSame('PDF mock content', $response->streamedContent());
    }
}
