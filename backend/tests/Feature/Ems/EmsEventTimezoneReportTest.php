<?php

namespace Tests\Feature\Ems;

use App\Ems\Jobs\GenerateReportJob;
use App\Ems\Models\Event;
use App\Ems\Models\Registration;
use App\Ems\Support\EmsRoles;
use App\Models\AnalyticsReport;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class EmsEventTimezoneReportTest extends EmsTestCase
{
    public function test_event_report_preserves_local_event_time_and_does_not_shift_630pm_to_130pm(): void
    {
        Storage::fake('local');

        $user = $this->emsUser(EmsRoles::SUPER_ADMIN);

        // Create an event set for 6:30 PM America/Vancouver (18:30 local)
        // 2026-09-24 18:30 America/Vancouver is 2026-09-25 01:30 UTC
        $localTime = Carbon::create(2026, 9, 24, 18, 30, 0, 'America/Vancouver');

        $event = Event::factory()->create([
            'name' => '630 PM Test Event',
            'timezone' => 'America/Vancouver',
            'start_at' => $localTime->copy()->setTimezone('UTC'),
            'end_at' => $localTime->copy()->addHours(2)->setTimezone('UTC'),
        ]);

        $reg = Registration::factory()->create([
            'event_id' => $event->id,
            'registered_at' => $localTime->copy()->subHour()->setTimezone('UTC'),
        ]);

        $report = AnalyticsReport::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'title' => 'Timezone Report Test',
            'type' => 'event_summary',
            'generated_by' => $user->id,
            'filters' => ['format' => 'csv', 'sections' => ['registrations' => true]],
        ]);

        // Execute report generation job
        (new GenerateReportJob($report->id, $event->uuid))->handle(app(\App\Ems\Services\AnalyticsService::class));

        $report->refresh();
        $this->assertNotNull($report->file_path);
        Storage::disk('local')->assertExists($report->file_path);

        $csvContent = Storage::disk('local')->get($report->file_path);

        // Verify CSV output contains 17:30 for registration/event time and NOT 00:30 UTC or 13:30 (1:30 PM)
        $this->assertStringContainsString('17:30:00', $csvContent); // registered 1 hour before 18:30 -> 17:30 Vancouver time
        $this->assertStringNotContainsString('00:30:00', $csvContent);
    }

    public function test_attendee_export_csv_formats_time_in_event_timezone(): void
    {
        $user = $this->emsUser(EmsRoles::SUPER_ADMIN);

        $localTime = Carbon::create(2026, 9, 24, 18, 30, 0, 'America/Vancouver');

        $event = Event::factory()->create([
            'timezone' => 'America/Vancouver',
            'start_at' => $localTime->copy()->setTimezone('UTC'),
        ]);

        $reg = Registration::factory()->create([
            'event_id' => $event->id,
            'created_at' => $localTime->copy()->setTimezone('UTC'),
        ]);

        $response = $this->actingAsEms($user)
            ->get("/api/v1/ems/events/{$event->uuid}/attendees/export-csv");

        $response->assertOk();
        $streamedContent = $response->streamedContent();

        // 18:30:00 should be in output, not 01:30:00 UTC
        $this->assertStringContainsString('18:30:00', $streamedContent);
    }
}
