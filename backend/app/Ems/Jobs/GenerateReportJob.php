<?php

namespace App\Ems\Jobs;

use App\Models\AnalyticsReport;
use App\Ems\Models\Event;
use App\Ems\Models\Registration;
use App\Ems\Models\Ticket;
use App\Ems\Models\CheckIn;
use App\Ems\Models\Payment;
use App\Ems\Support\EmsPermissions;
use App\Ems\Services\AnalyticsService;
use App\Notifications\SendAnalyticsReportNotification;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Throwable;

class GenerateReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 300; // 5 minutes max execution

    /**
     * Create a new job instance.
     */
    public function __construct(
        public readonly int $reportId,
        public readonly string $eventUuid
    ) {
        $this->onQueue((string) config('ems.operations.queue', 'ems-operations'));
    }

    /**
     * Execute the job.
     */
    public function handle(AnalyticsService $analyticsService): void
    {
        $report = AnalyticsReport::findOrFail($this->reportId);
        $event = Event::where('uuid', $this->eventUuid)->firstOrFail();
        $user = $report->generatedBy;

        if (!$user) {
            return;
        }

        $filters = $report->filters ?? [];
        $format = $filters['format'] ?? 'csv';
        $sections = $filters['sections'] ?? [
            'registrations' => true,
            'revenue' => true,
            'attendance' => true,
            'ticket_sales' => true,
            'payments' => true,
            'waitlist' => true,
            'check_ins' => true,
        ];

        // Gather statistics
        $payload = $analyticsService->getDashboardPayload($user, [
            'event_uuid' => $event->uuid,
            'start_date' => $filters['start_date'] ?? null,
            'end_date' => $filters['end_date'] ?? null,
        ]);

        // Role-based financial gating
        $showFinancial = $user->hasPermission(EmsPermissions::ANALYTICS_VIEW_FINANCIAL);

        // Gather raw collections for the custom sections
        $data = [];
        $eventIds = [$event->id];

        if (!empty($sections['registrations'])) {
            $data['registrations'] = Registration::whereIn('event_id', $eventIds)
                ->whereIn('status', ['confirmed', 'pending', 'awaiting_payment'])
                ->with('ticketType')
                ->orderBy('registered_at', 'desc')
                ->get();
        }

        if ($showFinancial && !empty($sections['payments'])) {
            $data['payments'] = Payment::whereIn('registration_id', function ($query) use ($eventIds) {
                $query->select('id')->from('ems_registrations')->whereIn('event_id', $eventIds);
            })->with('registration')->orderBy('paid_at', 'desc')->get();
        }

        if (!empty($sections['waitlist'])) {
            $data['waitlist'] = \App\Ems\Models\WaitlistEntry::whereIn('event_id', $eventIds)
                ->orderBy('position')
                ->get();
        }

        if (!empty($sections['check_ins'])) {
            $data['check_ins'] = CheckIn::whereIn('event_id', $eventIds)
                ->with(['ticket', 'checkedInBy'])
                ->orderBy('checked_in_at', 'desc')
                ->get();
        }

        $fileName = "reports/{$report->uuid}.{$format}";
        $content = '';

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('pdf.ems_event_report', [
                'title' => $report->title,
                'event' => $event,
                'kpis' => $payload['kpis'],
                'charts' => $payload['charts'],
                'sections' => $sections,
                'data' => $data,
                'show_financial' => $showFinancial,
                'generated_at' => now()->format('Y-m-d H:i:s'),
                'version' => '1.0',
                'user' => $user,
            ]);
            $content = $pdf->output();
        } elseif ($format === 'xlsx') {
            $content = $this->generateExcel($event, $payload['kpis'], $payload['charts'], $sections, $data, $showFinancial, $user);
        } else {
            // Default: CSV format
            $content = $this->generateCsv($event, $payload['kpis'], $sections, $data, $showFinancial);
        }

        // Store file
        Storage::disk('local')->put($fileName, $content);

        // Update report status
        $report->file_path = $fileName;
        $report->generated_at = now();
        $report->save();

        // Notify user
        $user->notify(new SendAnalyticsReportNotification($report));
    }

    /**
     * Generate Excel binary content.
     */
    private function generateExcel(
        Event $event,
        array $kpis,
        array $charts,
        array $sections,
        array $data,
        bool $showFinancial,
        User $user
    ): string {
        $spreadsheet = new Spreadsheet();

        // Sheet 1: Summary
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Event Summary');

        $sheet->setCellValue('A1', 'SFU MSA Event Summary Report');
        $sheet->mergeCells('A1:D1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);

        $sheet->setCellValue('A3', 'Event Title:');
        $sheet->setCellValue('B3', $event->name);
        $sheet->setCellValue('A4', 'Organizer:');
        $sheet->setCellValue('B4', $event->organizer->name ?? 'N/A');
        $sheet->setCellValue('A5', 'Date range:');
        $sheet->setCellValue('B5', ($event->start_at ? $event->start_at->format('Y-m-d') : '') . ' to ' . ($event->end_at ? $event->end_at->format('Y-m-d') : ''));
        $sheet->setCellValue('A6', 'Generated At:');
        $sheet->setCellValue('B6', now()->format('Y-m-d H:i:s'));

        // KPIs section
        $sheet->setCellValue('A8', 'KPI Metric');
        $sheet->setCellValue('B8', 'Value');
        $sheet->getStyle('A8:B8')->getFont()->setBold(true);

        $sheet->setCellValue('A9', 'Total Registrations');
        $sheet->setCellValue('B9', $kpis['total_registrations']);
        $sheet->setCellValue('A10', 'Checked In Attendees');
        $sheet->setCellValue('B10', $kpis['checked_in']);
        $sheet->setCellValue('A11', 'No Shows');
        $sheet->setCellValue('B11', $kpis['no_shows']);
        $sheet->setCellValue('A12', 'Attendance Rate');
        $sheet->setCellValue('B12', $kpis['attendance_rate'] . '%');

        $rowIdx = 13;
        if ($showFinancial) {
            $sheet->setCellValue('A' . $rowIdx, 'Gross Revenue');
            $sheet->setCellValue('B' . $rowIdx, '$' . number_format($kpis['gross_revenue'], 2));
            $rowIdx++;
            $sheet->setCellValue('A' . $rowIdx, 'Refunds');
            $sheet->setCellValue('B' . $rowIdx, '$' . number_format($kpis['refunds'], 2));
            $rowIdx++;
            $sheet->setCellValue('A' . $rowIdx, 'Net Revenue');
            $sheet->setCellValue('B' . $rowIdx, '$' . number_format($kpis['net_revenue'], 2));
            $rowIdx++;
            $sheet->setCellValue('A' . $rowIdx, 'Waitlist Size');
            $sheet->setCellValue('B' . $rowIdx, $kpis['waitlist_size']);
            $rowIdx++;
        }

        // Sheet 2: Registrations
        if (!empty($sections['registrations']) && !empty($data['registrations'])) {
            $sheetReg = $spreadsheet->createSheet();
            $sheetReg->setTitle('Registrations');

            $headers = ['Reference', 'Attendee Name', 'Email', 'Phone', 'Ticket Type', 'Status', 'Registered At'];
            $sheetReg->fromArray($headers, null, 'A1');
            $sheetReg->getStyle('A1:G1')->getFont()->setBold(true);

            $rows = [];
            foreach ($data['registrations'] as $reg) {
                $rows[] = [
                    $reg->reference,
                    $reg->attendee_name,
                    $reg->attendee_email,
                    $reg->attendee_phone ?? 'N/A',
                    $reg->ticketType->name ?? 'N/A',
                    $reg->status->value,
                    $reg->registered_at ? $reg->registered_at->format('Y-m-d H:i:s') : '',
                ];
            }
            $sheetReg->fromArray($rows, null, 'A2');
        }

        // Sheet 3: Payments
        if ($showFinancial && !empty($sections['payments']) && !empty($data['payments'])) {
            $sheetPay = $spreadsheet->createSheet();
            $sheetPay->setTitle('Payments');

            $headers = ['Transaction ID', 'Registration Ref', 'Amount', 'Refunded', 'Status', 'Paid At', 'Provider'];
            $sheetPay->fromArray($headers, null, 'A1');
            $sheetPay->getStyle('A1:G1')->getFont()->setBold(true);

            $rows = [];
            foreach ($data['payments'] as $payment) {
                $rows[] = [
                    $payment->provider_payment_id ?? $payment->uuid,
                    $payment->registration->reference ?? 'N/A',
                    (float) $payment->amount,
                    (float) $payment->amount_refunded,
                    $payment->status->value,
                    $payment->paid_at ? $payment->paid_at->format('Y-m-d H:i:s') : '',
                    $payment->provider->value,
                ];
            }
            $sheetPay->fromArray($rows, null, 'A2');
        }

        // Sheet 4: Waitlist
        if (!empty($sections['waitlist']) && !empty($data['waitlist'])) {
            $sheetWait = $spreadsheet->createSheet();
            $sheetWait->setTitle('Waitlist');

            $headers = ['Position', 'Attendee Name', 'Email', 'Phone', 'Quantity', 'Status', 'Joined At'];
            $sheetWait->fromArray($headers, null, 'A1');
            $sheetWait->getStyle('A1:G1')->getFont()->setBold(true);

            $rows = [];
            foreach ($data['waitlist'] as $entry) {
                $rows[] = [
                    (int) $entry->position,
                    $entry->attendee_name,
                    $entry->attendee_email,
                    $entry->attendee_phone ?? 'N/A',
                    (int) $entry->quantity,
                    $entry->status->value,
                    $entry->created_at ? $entry->created_at->format('Y-m-d H:i:s') : '',
                ];
            }
            $sheetWait->fromArray($rows, null, 'A2');
        }

        // Sheet 5: Check-ins
        if (!empty($sections['check_ins']) && !empty($data['check_ins'])) {
            $sheetCheck = $spreadsheet->createSheet();
            $sheetCheck->setTitle('Check-Ins');

            $headers = ['Ticket Code', 'Holder Name', 'Email', 'Checked In At', 'Method', 'Checked In By'];
            $sheetCheck->fromArray($headers, null, 'A1');
            $sheetCheck->getStyle('A1:F1')->getFont()->setBold(true);

            $rows = [];
            foreach ($data['check_ins'] as $check) {
                $rows[] = [
                    $check->ticket->code ?? 'N/A',
                    $check->ticket->holder_name ?? ($check->registration->attendee_name ?? 'N/A'),
                    $check->ticket->holder_email ?? ($check->registration->attendee_email ?? 'N/A'),
                    $check->checked_in_at ? $check->checked_in_at->format('Y-m-d H:i:s') : '',
                    $check->method->value,
                    $check->checkedInBy->name ?? 'System',
                ];
            }
            $sheetCheck->fromArray($rows, null, 'A2');
        }

        $writer = new Xlsx($spreadsheet);
        $tempPath = tempnam(sys_get_temp_dir(), 'xlsx');
        $writer->save($tempPath);
        $excelContent = file_get_contents($tempPath);
        unlink($tempPath);

        return $excelContent;
    }

    /**
     * Generate CSV content.
     */
    private function generateCsv(
        Event $event,
        array $kpis,
        array $sections,
        array $data,
        bool $showFinancial
    ): string {
        $stream = fopen('php://temp', 'r+');

        // Event Metadata
        fputcsv($stream, ['SFU MSA Event Summary Report']);
        fputcsv($stream, ['Event Name', $event->name]);
        fputcsv($stream, ['Generated At', now()->format('Y-m-d H:i:s')]);
        fputcsv($stream, []);

        // KPIs
        fputcsv($stream, ['KPI Metrics']);
        fputcsv($stream, ['Total Registrations', $kpis['total_registrations']]);
        fputcsv($stream, ['Checked In Attendees', $kpis['checked_in']]);
        fputcsv($stream, ['No Shows', $kpis['no_shows']]);
        fputcsv($stream, ['Attendance Rate', $kpis['attendance_rate'] . '%']);

        if ($showFinancial) {
            fputcsv($stream, ['Gross Revenue', '$' . number_format($kpis['gross_revenue'], 2)]);
            fputcsv($stream, ['Refunds', '$' . number_format($kpis['refunds'], 2)]);
            fputcsv($stream, ['Net Revenue', '$' . number_format($kpis['net_revenue'], 2)]);
            fputcsv($stream, ['Waitlist Size', $kpis['waitlist_size']]);
        }
        fputcsv($stream, []);

        // Registrations
        if (!empty($sections['registrations']) && !empty($data['registrations'])) {
            fputcsv($stream, ['--- Registrations ---']);
            fputcsv($stream, ['Reference', 'Attendee Name', 'Email', 'Phone', 'Ticket Type', 'Status', 'Registered At']);
            foreach ($data['registrations'] as $reg) {
                fputcsv($stream, [
                    $reg->reference,
                    $reg->attendee_name,
                    $reg->attendee_email,
                    $reg->attendee_phone ?? 'N/A',
                    $reg->ticketType->name ?? 'N/A',
                    $reg->status->value,
                    $reg->registered_at ? $reg->registered_at->format('Y-m-d H:i:s') : '',
                ]);
            }
            fputcsv($stream, []);
        }

        // Payments
        if ($showFinancial && !empty($sections['payments']) && !empty($data['payments'])) {
            fputcsv($stream, ['--- Payments ---']);
            fputcsv($stream, ['Transaction ID', 'Registration Ref', 'Amount', 'Refunded', 'Status', 'Paid At', 'Provider']);
            foreach ($data['payments'] as $payment) {
                fputcsv($stream, [
                    $payment->provider_payment_id ?? $payment->uuid,
                    $payment->registration->reference ?? 'N/A',
                    $payment->amount,
                    $payment->amount_refunded,
                    $payment->status->value,
                    $payment->paid_at ? $payment->paid_at->format('Y-m-d H:i:s') : '',
                    $payment->provider->value,
                ]);
            }
            fputcsv($stream, []);
        }

        // Waitlist
        if (!empty($sections['waitlist']) && !empty($data['waitlist'])) {
            fputcsv($stream, ['--- Waitlist ---']);
            fputcsv($stream, ['Position', 'Attendee Name', 'Email', 'Phone', 'Quantity', 'Status', 'Joined At']);
            foreach ($data['waitlist'] as $entry) {
                fputcsv($stream, [
                    $entry->position,
                    $entry->attendee_name,
                    $entry->attendee_email,
                    $entry->attendee_phone ?? 'N/A',
                    $entry->quantity,
                    $entry->status->value,
                    $entry->created_at ? $entry->created_at->format('Y-m-d H:i:s') : '',
                ]);
            }
            fputcsv($stream, []);
        }

        // Check-ins
        if (!empty($sections['check_ins']) && !empty($data['check_ins'])) {
            fputcsv($stream, ['--- Check-ins ---']);
            fputcsv($stream, ['Ticket Code', 'Holder Name', 'Email', 'Checked In At', 'Method', 'Checked In By']);
            foreach ($data['check_ins'] as $check) {
                fputcsv($stream, [
                    $check->ticket->code ?? 'N/A',
                    $check->ticket->holder_name ?? ($check->registration->attendee_name ?? 'N/A'),
                    $check->ticket->holder_email ?? ($check->registration->attendee_email ?? 'N/A'),
                    $check->checked_in_at ? $check->checked_in_at->format('Y-m-d H:i:s') : '',
                    $check->method->value,
                    $check->checkedInBy->name ?? 'System',
                ]);
            }
            fputcsv($stream, []);
        }

        rewind($stream);
        $csvContent = stream_get_contents($stream);
        fclose($stream);

        return $csvContent;
    }

    /**
     * Handle job failure.
     */
    public function failed(Throwable $exception): void
    {
        $report = AnalyticsReport::find($this->reportId);
        if ($report) {
            $report->file_path = null;
            $report->title = $report->title . ' (Failed)';
            $report->save();
        }
    }
}
