<?php

namespace App\Pm\Services;

use App\Models\User;
use App\Pm\Models\PmProject;
use App\Pm\Models\PmTask;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class PmExportService
{
    public function __construct(
        protected PmAnalyticsService $analyticsService,
        protected PmWorkloadService $workloadService
    ) {}

    /**
     * Neutralize spreadsheet formula injection (CWE-1236) in untrusted text fields.
     */
    private function sanitizeSpreadsheetField(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        // Avoid double-prefixing if already neutralized
        if ($value[0] === "'") {
            return $value;
        }

        // Neutralize strings starting with whitespace control characters
        if ($value[0] === "\t" || $value[0] === "\r" || $value[0] === "\n") {
            return "'" . $value;
        }

        // Inspect the first meaningful character, accounting for leading whitespace/control characters
        $firstMeaningful = ltrim($value, " \t\n\r\0\x0B\xC2\xA0");

        if (
            $firstMeaningful !== '' &&
            in_array($firstMeaningful[0], ['=', '+', '-', '@', '|', '%'], true)
        ) {
            return "'" . $value;
        }

        return $value;
    }

    /**
     * Export Project Progress Report in CSV, XLSX, or PDF.
     */
    public function exportProjectProgress(PmProject $project, User $viewer, string $format = 'csv'): array
    {
        $project->loadMissing(['department', 'owner']);
        $analytics = $this->analyticsService->getProjectAnalytics($project, $viewer);
        
        $milestones = $project->milestones()
            ->withCount([
                'tasks',
                'tasks as completed_tasks_count' => function ($q) {
                    $q->where('status', 'completed');
                },
            ])
            ->orderBy('due_date')
            ->get();

        $tasks = $project->tasks()
            ->with(['assignee', 'taskList'])
            ->orderByRaw("CASE WHEN status = 'completed' THEN 2 ELSE 1 END")
            ->orderBy('due_date')
            ->limit(500)
            ->get();

        $metrics = [
            'completion_rate' => $analytics['project']['completion_rate'] ?? 0,
            'total_tasks' => $analytics['task_counts']['total'] ?? 0,
            'completed_tasks' => $analytics['task_counts']['completed'] ?? 0,
            'in_progress_tasks' => $analytics['task_counts']['in_progress'] ?? 0,
            'overdue_tasks' => $analytics['task_counts']['overdue'] ?? 0,
            'total_hours' => $analytics['effort_hours']['total'] ?? 0,
            'remaining_hours' => $analytics['effort_hours']['remaining'] ?? 0,
        ];

        $totalTasksCount = (int) ($metrics['total_tasks'] ?? count($tasks));
        $isTasksTruncated = $totalTasksCount > count($tasks);

        $filenameBase = 'pms-project-' . ($project->code ? strtolower($project->code) : 'proj-' . $project->id) . '-progress-' . now()->format('Ymd');

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('pdf.pm_project_report', [
                'project' => $project,
                'metrics' => $metrics,
                'milestones' => $milestones,
                'tasks' => $tasks,
                'generatedBy' => $viewer,
                'totalTasksCount' => $totalTasksCount,
                'isTasksTruncated' => $isTasksTruncated,
            ]);

            return [
                'content' => $pdf->output(),
                'filename' => $filenameBase . '.pdf',
                'contentType' => 'application/pdf',
            ];
        }

        if ($format === 'xlsx') {
            $spreadsheet = new Spreadsheet();

            // Sheet 1: Project Overview
            $sheet1 = $spreadsheet->getActiveSheet();
            $sheet1->setTitle('Overview');

            $sheet1->setCellValue('A1', 'SFU MSA PMS - Project Progress Report');
            $sheet1->getStyle('A1')->getFont()->setBold(true)->setSize(14);

            $sheet1->setCellValue('A3', 'Project Name:');
            $sheet1->setCellValueExplicit('B3', $this->sanitizeSpreadsheetField($project->name), DataType::TYPE_STRING);
            $sheet1->setCellValue('A4', 'Project Code:');
            $sheet1->setCellValueExplicit('B4', $this->sanitizeSpreadsheetField($project->code ?? 'N/A'), DataType::TYPE_STRING);
            $sheet1->setCellValue('A5', 'Department:');
            $sheet1->setCellValueExplicit('B5', $this->sanitizeSpreadsheetField($project->department->name ?? 'Universal'), DataType::TYPE_STRING);
            $sheet1->setCellValue('A6', 'Status:');
            $sheet1->setCellValueExplicit('B6', ucfirst(str_replace('_', ' ', $project->status->value ?? $project->status)), DataType::TYPE_STRING);
            $sheet1->setCellValue('A7', 'Completion Rate:');
            $sheet1->setCellValueExplicit('B7', $metrics['completion_rate'] . '%', DataType::TYPE_STRING);
            $sheet1->setCellValue('A8', 'Total Tasks:');
            $sheet1->setCellValue('B8', (int) $metrics['total_tasks']);
            $sheet1->setCellValue('A9', 'Overdue Tasks:');
            $sheet1->setCellValue('B9', (int) $metrics['overdue_tasks']);
            $sheet1->setCellValue('A10', 'Remaining Effort (hrs):');
            $sheet1->setCellValue('B10', (float) $metrics['remaining_hours']);
            $sheet1->getStyle('A3:A10')->getFont()->setBold(true);

            // Milestones table in Sheet 1
            $row = 12;
            $sheet1->setCellValue('A' . $row, 'Milestones');
            $sheet1->getStyle('A' . $row)->getFont()->setBold(true)->setSize(12);
            $row++;
            $sheet1->fromArray(['Title', 'Target Date', 'Status', 'Tasks Count', 'Completed Tasks'], null, 'A' . $row);
            $sheet1->getStyle('A' . $row . ':E' . $row)->getFont()->setBold(true);
            $row++;
            foreach ($milestones as $ms) {
                $statusStr = $ms->status instanceof \BackedEnum ? $ms->status->value : (string) ($ms->status ?? 'pending');
                $sheet1->setCellValueExplicit('A' . $row, $this->sanitizeSpreadsheetField($ms->title), DataType::TYPE_STRING);
                $sheet1->setCellValueExplicit('B' . $row, $ms->due_date ? Carbon::parse($ms->due_date)->format('Y-m-d') : 'No Date', DataType::TYPE_STRING);
                $sheet1->setCellValueExplicit('C' . $row, ucfirst(str_replace('_', ' ', $statusStr)), DataType::TYPE_STRING);
                $sheet1->setCellValue('D' . $row, (int) $ms->tasks_count);
                $sheet1->setCellValue('E' . $row, (int) $ms->completed_tasks_count);
                $row++;
            }

            // Sheet 2: Tasks List
            $sheet2 = $spreadsheet->createSheet();
            $sheet2->setTitle('Tasks');
            $headers = ['Task Title', 'Section', 'Assignee', 'Status', 'Priority', 'Start Date', 'Due Date', 'Est. Hours', 'Completed At'];
            $sheet2->fromArray($headers, null, 'A1');
            $sheet2->getStyle('A1:I1')->getFont()->setBold(true);

            $row = 2;
            foreach ($tasks as $t) {
                $sheet2->setCellValueExplicit('A' . $row, $this->sanitizeSpreadsheetField($t->title), DataType::TYPE_STRING);
                $sheet2->setCellValueExplicit('B' . $row, $this->sanitizeSpreadsheetField($t->taskList->name ?? $t->taskList->title ?? 'Default'), DataType::TYPE_STRING);
                $sheet2->setCellValueExplicit('C' . $row, $this->sanitizeSpreadsheetField($t->assignee->name ?? 'Unassigned'), DataType::TYPE_STRING);
                $sheet2->setCellValueExplicit('D' . $row, ucfirst(str_replace('_', ' ', $t->status->value ?? $t->status)), DataType::TYPE_STRING);
                $sheet2->setCellValueExplicit('E' . $row, ucfirst($t->priority->value ?? $t->priority), DataType::TYPE_STRING);
                $sheet2->setCellValueExplicit('F' . $row, $t->start_date ? Carbon::parse($t->start_date)->format('Y-m-d') : '', DataType::TYPE_STRING);
                $sheet2->setCellValueExplicit('G' . $row, $t->due_date ? Carbon::parse($t->due_date)->format('Y-m-d') : '', DataType::TYPE_STRING);
                if ($t->estimated_hours !== null && is_numeric($t->estimated_hours)) {
                    $sheet2->setCellValue('H' . $row, (float) $t->estimated_hours);
                } else {
                    $sheet2->setCellValueExplicit('H' . $row, '', DataType::TYPE_STRING);
                }
                $sheet2->setCellValueExplicit('I' . $row, $t->completed_at ? Carbon::parse($t->completed_at)->format('Y-m-d H:i') : '', DataType::TYPE_STRING);
                $row++;
            }

            $writer = new Xlsx($spreadsheet);
            $tempPath = tempnam(sys_get_temp_dir(), 'xlsx_pms');
            $writer->save($tempPath);
            $content = file_get_contents($tempPath);
            @unlink($tempPath);

            return [
                'content' => $content,
                'filename' => $filenameBase . '.xlsx',
                'contentType' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ];
        }

        // Default: CSV
        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, ['SFU MSA PMS - Project Progress Report']);
        fputcsv($stream, ['Project Name', $this->sanitizeSpreadsheetField($project->name)]);
        fputcsv($stream, ['Project Code', $this->sanitizeSpreadsheetField($project->code ?? 'N/A')]);
        fputcsv($stream, ['Department', $this->sanitizeSpreadsheetField($project->department->name ?? 'Universal')]);
        fputcsv($stream, ['Status', ucfirst(str_replace('_', ' ', $project->status->value ?? $project->status))]);
        fputcsv($stream, ['Generated At', now()->format('Y-m-d H:i:s') . ' UTC']);
        fputcsv($stream, []);

        fputcsv($stream, ['--- Key Metrics ---']);
        fputcsv($stream, ['Completion Rate', $metrics['completion_rate'] . '%']);
        fputcsv($stream, ['Total Tasks', $metrics['total_tasks']]);
        fputcsv($stream, ['Completed Tasks', $metrics['completed_tasks']]);
        fputcsv($stream, ['In Progress Tasks', $metrics['in_progress_tasks']]);
        fputcsv($stream, ['Overdue Tasks', $metrics['overdue_tasks']]);
        fputcsv($stream, ['Total Estimated Hours', $metrics['total_hours']]);
        fputcsv($stream, ['Remaining Estimated Hours', $metrics['remaining_hours']]);
        fputcsv($stream, []);

        if ($milestones->isNotEmpty()) {
            fputcsv($stream, ['--- Milestones ---']);
            fputcsv($stream, ['Title', 'Target Date', 'Status', 'Tasks Count', 'Completed Tasks']);
            foreach ($milestones as $ms) {
                $statusStr = $ms->status instanceof \BackedEnum ? $ms->status->value : (string) ($ms->status ?? 'pending');
                fputcsv($stream, [
                    $this->sanitizeSpreadsheetField($ms->title),
                    $ms->due_date ? Carbon::parse($ms->due_date)->format('Y-m-d') : 'No Date',
                    ucfirst(str_replace('_', ' ', $statusStr)),
                    $ms->tasks_count,
                    $ms->completed_tasks_count,
                ]);
            }
            fputcsv($stream, []);
        }

        fputcsv($stream, ['--- Tasks ---']);
        if ($isTasksTruncated) {
            fputcsv($stream, ['Notice', "Showing first " . count($tasks) . " of {$totalTasksCount} tasks (capped at 500). Filter project tasks to view complete set."]);
        }
        fputcsv($stream, ['Task Title', 'Section', 'Assignee', 'Status', 'Priority', 'Start Date', 'Due Date', 'Est. Hours']);
        foreach ($tasks as $t) {
            fputcsv($stream, [
                $this->sanitizeSpreadsheetField($t->title),
                $this->sanitizeSpreadsheetField($t->taskList->name ?? $t->taskList->title ?? 'Default'),
                $this->sanitizeSpreadsheetField($t->assignee->name ?? 'Unassigned'),
                ucfirst(str_replace('_', ' ', $t->status->value ?? $t->status)),
                ucfirst($t->priority->value ?? $t->priority),
                $t->start_date ? Carbon::parse($t->start_date)->format('Y-m-d') : '',
                $t->due_date ? Carbon::parse($t->due_date)->format('Y-m-d') : '',
                $t->estimated_hours ?? '',
            ]);
        }

        rewind($stream);
        $csvContent = stream_get_contents($stream);
        fclose($stream);

        return [
            'content' => "\xEF\xBB\xBF" . $csvContent, // Prepend UTF-8 BOM
            'filename' => $filenameBase . '.csv',
            'contentType' => 'text/csv; charset=UTF-8',
        ];
    }

    /**
     * Export Tasks List in CSV, XLSX, or PDF.
     */
    public function exportTasks(
        Collection $tasks,
        User $viewer,
        string $format = 'csv',
        string $title = 'Tasks Export',
        ?int $totalMatching = null,
        int $limit = 1000
    ): array {
        $filenameBase = 'pms-tasks-' . now()->format('Ymd-His');
        $total = $totalMatching ?? count($tasks);
        $isTruncated = $total > count($tasks);

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('pdf.pm_tasks_report', [
                'title' => $title,
                'tasks' => $tasks,
                'generatedBy' => $viewer,
                'totalMatching' => $total,
                'limit' => $limit,
                'isTruncated' => $isTruncated,
            ]);

            return [
                'content' => $pdf->output(),
                'filename' => $filenameBase . '.pdf',
                'contentType' => 'application/pdf',
            ];
        }

        if ($format === 'xlsx') {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Tasks');

            $row = 1;
            $sheet->setCellValue('A' . $row, 'SFU MSA PMS - ' . $this->sanitizeSpreadsheetField($title));
            $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(12);
            $row++;

            $sheet->setCellValue('A' . $row, 'Generated: ' . now()->format('Y-m-d H:i:s') . ' UTC | Total Matching Tasks: ' . $total . ' | Exported Tasks: ' . count($tasks));
            $sheet->getStyle('A' . $row)->getFont()->setSize(10)->getColor()->setRGB('6B7280');
            $row++;

            if ($isTruncated) {
                $sheet->setCellValue('A' . $row, 'NOTICE: Export truncated. Displaying first ' . count($tasks) . ' of ' . $total . ' matching tasks (limit: ' . $limit . '). Apply filters to view remaining tasks.');
                $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(10)->getColor()->setRGB('B91C1C');
                $row++;
            }

            $row++; // spacing row
            $headerRow = $row;
            $headers = ['Task Title', 'Project', 'Section', 'Assignee', 'Status', 'Priority', 'Start Date', 'Due Date', 'Est. Hours', 'Completed At'];
            $sheet->fromArray($headers, null, 'A' . $headerRow);
            $sheet->getStyle('A' . $headerRow . ':J' . $headerRow)->getFont()->setBold(true);

            $row = $headerRow + 1;
            foreach ($tasks as $t) {
                $sheet->setCellValueExplicit('A' . $row, $this->sanitizeSpreadsheetField($t->title), DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('B' . $row, $this->sanitizeSpreadsheetField($t->project->name ?? 'N/A'), DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('C' . $row, $this->sanitizeSpreadsheetField($t->taskList->name ?? $t->taskList->title ?? 'Default'), DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('D' . $row, $this->sanitizeSpreadsheetField($t->assignee->name ?? 'Unassigned'), DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('E' . $row, ucfirst(str_replace('_', ' ', $t->status->value ?? $t->status)), DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('F' . $row, ucfirst($t->priority->value ?? $t->priority), DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('G' . $row, $t->start_date ? Carbon::parse($t->start_date)->format('Y-m-d') : '', DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('H' . $row, $t->due_date ? Carbon::parse($t->due_date)->format('Y-m-d') : '', DataType::TYPE_STRING);
                if ($t->estimated_hours !== null && is_numeric($t->estimated_hours)) {
                    $sheet->setCellValue('I' . $row, (float) $t->estimated_hours);
                } else {
                    $sheet->setCellValueExplicit('I' . $row, '', DataType::TYPE_STRING);
                }
                $sheet->setCellValueExplicit('J' . $row, $t->completed_at ? Carbon::parse($t->completed_at)->format('Y-m-d H:i') : '', DataType::TYPE_STRING);
                $row++;
            }

            $writer = new Xlsx($spreadsheet);
            $tempPath = tempnam(sys_get_temp_dir(), 'xlsx_pms_tasks');
            $writer->save($tempPath);
            $content = file_get_contents($tempPath);
            @unlink($tempPath);

            return [
                'content' => $content,
                'filename' => $filenameBase . '.xlsx',
                'contentType' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ];
        }

        // CSV
        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, ['SFU MSA PMS - ' . $this->sanitizeSpreadsheetField($title)]);
        fputcsv($stream, ['Generated At', now()->format('Y-m-d H:i:s') . ' UTC']);
        fputcsv($stream, ['Total Matching Tasks', $total]);
        fputcsv($stream, ['Exported Tasks', count($tasks)]);
        if ($isTruncated) {
            fputcsv($stream, ['NOTICE', "Export truncated. Displaying first " . count($tasks) . " of {$total} matching tasks (limit: {$limit}). Filter by project, status, priority, or date range to view remaining tasks."]);
        }
        fputcsv($stream, []);

        fputcsv($stream, ['Task Title', 'Project', 'Section', 'Assignee', 'Status', 'Priority', 'Start Date', 'Due Date', 'Est. Hours']);
        foreach ($tasks as $t) {
            fputcsv($stream, [
                $this->sanitizeSpreadsheetField($t->title),
                $this->sanitizeSpreadsheetField($t->project->name ?? 'N/A'),
                $this->sanitizeSpreadsheetField($t->taskList->name ?? $t->taskList->title ?? 'Default'),
                $this->sanitizeSpreadsheetField($t->assignee->name ?? 'Unassigned'),
                ucfirst(str_replace('_', ' ', $t->status->value ?? $t->status)),
                ucfirst($t->priority->value ?? $t->priority),
                $t->start_date ? Carbon::parse($t->start_date)->format('Y-m-d') : '',
                $t->due_date ? Carbon::parse($t->due_date)->format('Y-m-d') : '',
                $t->estimated_hours ?? '',
            ]);
        }

        rewind($stream);
        $csvContent = stream_get_contents($stream);
        fclose($stream);

        return [
            'content' => "\xEF\xBB\xBF" . $csvContent,
            'filename' => $filenameBase . '.csv',
            'contentType' => 'text/csv; charset=UTF-8',
        ];
    }

    /**
     * Export Workload Allocation in CSV, XLSX, or PDF.
     */
    public function exportProjectWorkload(PmProject $project, User $viewer, string $startDate, string $endDate, string $format = 'csv'): array
    {
        $workloadResult = $this->workloadService->getProjectTeamWorkload($project, $startDate, $endDate, $viewer);
        $workloadData = $workloadResult['team_workload'] ?? [];

        $filenameBase = 'pms-project-' . ($project->code ? strtolower($project->code) : 'proj-' . $project->id) . '-workload-' . now()->format('Ymd');
        $title = "Workload Allocation - {$project->name}";

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('pdf.pm_workload_report', [
                'title' => $title,
                'startDate' => $startDate,
                'endDate' => $endDate,
                'workloadData' => $workloadData,
                'generatedBy' => $viewer,
            ]);

            return [
                'content' => $pdf->output(),
                'filename' => $filenameBase . '.pdf',
                'contentType' => 'application/pdf',
            ];
        }

        if ($format === 'xlsx') {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Workload');

            $headers = ['Contributor', 'Email', 'Weekly Capacity (hrs)', 'Allocated Hours', 'Active Tasks', 'Utilization Rate (%)'];
            $sheet->fromArray($headers, null, 'A1');
            $sheet->getStyle('A1:F1')->getFont()->setBold(true);

            $row = 2;
            foreach ($workloadData as $item) {
                $sheet->setCellValueExplicit('A' . $row, $this->sanitizeSpreadsheetField($item['user']['name'] ?? 'Team Member'), DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('B' . $row, $this->sanitizeSpreadsheetField($item['user']['email'] ?? 'N/A'), DataType::TYPE_STRING);
                $sheet->setCellValue('C' . $row, (float) ($item['weekly_capacity_hours'] ?? 10));
                $sheet->setCellValue('D' . $row, (float) ($item['total_allocated_hours'] ?? 0));
                $sheet->setCellValue('E' . $row, (int) ($item['active_tasks_count'] ?? 0));
                $sheet->setCellValue('F' . $row, (float) ($item['utilization_rate'] ?? 0));
                $row++;
            }

            $writer = new Xlsx($spreadsheet);
            $tempPath = tempnam(sys_get_temp_dir(), 'xlsx_pms_workload');
            $writer->save($tempPath);
            $content = file_get_contents($tempPath);
            @unlink($tempPath);

            return [
                'content' => $content,
                'filename' => $filenameBase . '.xlsx',
                'contentType' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ];
        }

        // CSV
        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, ['SFU MSA PMS - ' . $this->sanitizeSpreadsheetField($title)]);
        fputcsv($stream, ['Period', "{$startDate} to {$endDate}"]);
        fputcsv($stream, ['Generated At', now()->format('Y-m-d H:i:s') . ' UTC']);
        fputcsv($stream, []);

        fputcsv($stream, ['Contributor', 'Email', 'Weekly Capacity (hrs)', 'Allocated Hours', 'Active Tasks', 'Utilization Rate (%)']);
        foreach ($workloadData as $item) {
            fputcsv($stream, [
                $this->sanitizeSpreadsheetField($item['user']['name'] ?? 'Team Member'),
                $this->sanitizeSpreadsheetField($item['user']['email'] ?? 'N/A'),
                $item['weekly_capacity_hours'] ?? 10,
                $item['total_allocated_hours'] ?? 0,
                $item['active_tasks_count'] ?? 0,
                ($item['utilization_rate'] ?? 0) . '%',
            ]);
        }

        rewind($stream);
        $csvContent = stream_get_contents($stream);
        fclose($stream);

        return [
            'content' => "\xEF\xBB\xBF" . $csvContent,
            'filename' => $filenameBase . '.csv',
            'contentType' => 'text/csv; charset=UTF-8',
        ];
    }
}
