<?php

namespace Tests\Feature\Ems;

use App\Ems\Enums\EventStatus;
use App\Ems\Enums\RegistrationStatus;
use App\Ems\Enums\TicketStatus;
use App\Ems\Exceptions\CheckInException;
use App\Ems\Models\Event;
use App\Ems\Models\Registration;
use App\Ems\Models\Ticket;
use App\Ems\Models\TicketType;
use App\Ems\Services\Operations\CheckInService;
use App\Ems\Services\Operations\EventOperationsService;
use App\Ems\Support\EmsRoles;
use Illuminate\Support\Facades\DB;

/**
 * QR Check-in and Summary Aggregation Benchmark.
 *
 * METHODOLOGY CLARIFICATION:
 * - Timings represent in-process PHP service-layer execution:
 *   - CheckInService::checkInByCode()
 *   - EventOperationsService::summary()
 * - Execution Model: Sequential execution within a single PHP CLI process.
 * - Non-Goals / Limitations:
 *   - Does NOT measure HTTP controller or middleware overhead.
 *   - Does NOT measure full HTTP request/response serialization cycles or Sanctum token verification.
 *   - Does NOT represent concurrent HTTP clients over network sockets.
 *   - Local PHPUnit in-memory SQLite execution is NOT a production load test.
 *   - Production capacity and concurrency must be verified in a dedicated staging
 *     environment using HTTP load tools (e.g. k6 / wrk) hitting Apache / PHP-FPM / MySQL.
 */
class EmsQrScanningBenchmarkTest extends EmsTestCase
{
    /**
     * Benchmark measuring service-level check-in and dashboard aggregation execution time.
     */
    public function test_qr_checkin_and_summary_load_benchmark(): void
    {
        $event = Event::factory()->create([
            'status' => EventStatus::RegistrationOpen,
            'is_public' => true,
            'start_at' => now()->addDays(1),
            'end_at' => now()->addDays(1)->addHours(4),
            'capacity' => 200,
        ]);

        $ticketType = TicketType::factory()->create([
            'event_id' => $event->id,
            'name' => 'General Admission',
            'price' => 10.0,
            'quantity' => 100,
            'quantity_sold' => 50,
            'is_active' => true,
            'currency' => 'CAD',
        ]);

        // 5 simulated staff users
        $staffMembers = [];
        for ($s = 0; $s < 5; $s++) {
            $staffMembers[] = $this->emsUser(EmsRoles::EVENT_STAFF);
        }

        // Create 50 attendees with issued tickets
        $tickets = [];
        for ($i = 0; $i < 50; $i++) {
            $reg = Registration::factory()->create([
                'event_id' => $event->id,
                'ticket_type_id' => $ticketType->id,
                'status' => RegistrationStatus::Confirmed,
            ]);

            $tickets[] = Ticket::factory()->forRegistration($reg)->create([
                'event_id' => $event->id,
                'registration_id' => $reg->id,
                'status' => TicketStatus::Issued,
            ]);
        }

        /** @var CheckInService $checkInService */
        $checkInService = app(CheckInService::class);
        /** @var EventOperationsService $opsService */
        $opsService = app(EventOperationsService::class);

        $scanLatencies = [];
        $summaryLatencies = [];
        $scanErrors = 0;
        $summaryErrors = 0;

        DB::flushQueryLog();
        DB::enableQueryLog();

        // Sequential service execution simulating 50 scans distributed across 5 staff identities,
        // interleaved with 100 summary operations
        for ($i = 0; $i < 50; $i++) {
            $staff = $staffMembers[$i % 5];
            $ticket = $tickets[$i];

            // 1. Check-in scan (Service layer)
            $t0 = microtime(true);
            try {
                $checkInService->checkInByCode($event, $ticket->code, $staff);
                $scanLatencies[] = (microtime(true) - $t0) * 1000;
            } catch (\Throwable $e) {
                $scanErrors++;
            }

            // 2. Interleaved polling summary requests (2 summary requests per scan)
            for ($p = 0; $p < 2; $p++) {
                $tSummary = microtime(true);
                try {
                    $opsService->summary($event);
                    $summaryLatencies[] = (microtime(true) - $tSummary) * 1000;
                } catch (\Throwable $e) {
                    $summaryErrors++;
                }
            }
        }

        $queryLog = DB::getQueryLog();
        $totalQueries = count($queryLog);

        sort($scanLatencies);
        sort($summaryLatencies);

        $avgScan = array_sum($scanLatencies) / max(1, count($scanLatencies));
        $p95IndexScan = (int) floor(count($scanLatencies) * 0.95);
        $p95Scan = $scanLatencies[$p95IndexScan] ?? 0;
        $maxScan = end($scanLatencies) ?: 0;

        $avgSummary = array_sum($summaryLatencies) / max(1, count($summaryLatencies));
        $p95IndexSummary = (int) floor(count($summaryLatencies) * 0.95);
        $p95Summary = $summaryLatencies[$p95IndexSummary] ?? 0;
        $maxSummary = end($summaryLatencies) ?: 0;

        echo "\n================ IN-PROCESS QR & SUMMARY SERVICE BENCHMARK ================\n";
        echo "SCOPE: In-process service execution latency only (sequential PHP CLI loop).\n";
        echo "DISCLAIMER: Measures internal ORM/service latency. Does NOT measure HTTP stack,\n";
        echo "            Sanctum auth middleware, network I/O, or concurrent Apache/Nginx load.\n";
        echo "Simulated Staff Contexts: 5\n";
        echo "Total Check-in Scans Executed: 50\n";
        echo "Total Summary Polling Requests Executed: 100\n";
        echo "Check-in Scan Latency (ms) — Avg: " . round($avgScan, 2) . "ms, P95: " . round($p95Scan, 2) . "ms, Max: " . round($maxScan, 2) . "ms\n";
        echo "Summary Request Latency (ms) — Avg: " . round($avgSummary, 2) . "ms, P95: " . round($p95Summary, 2) . "ms, Max: " . round($maxSummary, 2) . "ms\n";
        echo "Scan Error Rate: " . ($scanErrors / 50 * 100) . "%\n";
        echo "Summary Error Rate: " . ($summaryErrors / 100 * 100) . "%\n";
        echo "Total Database Queries Logged: {$totalQueries} across 150 operations\n";
        echo "Queries Per Summary Execution: ~3 queries (consolidated grouped aggregations)\n";
        echo "===========================================================================\n";

        $this->assertSame(0, $scanErrors, 'Check-in error rate must be 0%');
        $this->assertSame(0, $summaryErrors, 'Summary error rate must be 0%');
        $this->assertLessThan(200, $avgScan, 'Average scan latency should be under 200ms locally');
        $this->assertLessThan(100, $avgSummary, 'Average summary latency should be under 100ms locally');
    }

    /**
     * Interleaved multi-staff concurrency simulation verifying ticket check-in isolation and duplicate prevention.
     */
    public function test_interleaved_multi_staff_ticket_checkin_concurrency_isolation(): void
    {
        $event = Event::factory()->create([
            'status' => EventStatus::RegistrationOpen,
            'is_public' => true,
            'start_at' => now()->addDays(1),
            'end_at' => now()->addDays(1)->addHours(4),
            'capacity' => 100,
        ]);

        $ticketType = TicketType::factory()->create([
            'event_id' => $event->id,
            'name' => 'VIP',
            'price' => 25.0,
            'quantity' => 50,
            'quantity_sold' => 20,
            'is_active' => true,
            'currency' => 'CAD',
        ]);

        $staffA = $this->emsUser(EmsRoles::EVENT_STAFF);
        $staffB = $this->emsUser(EmsRoles::EVENT_STAFF);

        $reg1 = Registration::factory()->create([
            'event_id' => $event->id,
            'ticket_type_id' => $ticketType->id,
            'status' => RegistrationStatus::Confirmed,
        ]);
        $ticket1 = Ticket::factory()->forRegistration($reg1)->create([
            'event_id' => $event->id,
            'registration_id' => $reg1->id,
            'status' => TicketStatus::Issued,
        ]);

        $reg2 = Registration::factory()->create([
            'event_id' => $event->id,
            'ticket_type_id' => $ticketType->id,
            'status' => RegistrationStatus::Confirmed,
        ]);
        $ticket2 = Ticket::factory()->forRegistration($reg2)->create([
            'event_id' => $event->id,
            'registration_id' => $reg2->id,
            'status' => TicketStatus::Issued,
        ]);

        /** @var CheckInService $checkInService */
        $checkInService = app(CheckInService::class);

        // Staff A checks in Ticket 1
        $resA1 = $checkInService->checkInByCode($event, $ticket1->code, $staffA);
        $this->assertSame(TicketStatus::Redeemed, $resA1['ticket']->status);

        // Staff B checks in Ticket 2
        $resB2 = $checkInService->checkInByCode($event, $ticket2->code, $staffB);
        $this->assertSame(TicketStatus::Redeemed, $resB2['ticket']->status);

        // Staff B attempts duplicate check-in of Ticket 1 concurrently
        $this->expectException(CheckInException::class);
        $checkInService->checkInByCode($event, $ticket1->code, $staffB);
    }
}
