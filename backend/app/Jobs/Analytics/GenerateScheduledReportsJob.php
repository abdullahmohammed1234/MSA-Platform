<?php

namespace App\Jobs\Analytics;

use App\Models\AnalyticsReport;
use App\Models\AnalyticsMetric;
use App\Models\User;
use App\Notifications\SendAnalyticsReportNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class GenerateScheduledReportsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $period;

    /**
     * Create a new job instance.
     */
    public function __construct(string $period = 'daily')
    {
        $this->period = $period;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $uuid = (string) Str::uuid();
        $title = ucfirst($this->period) . " Analytics Summary - " . Carbon::today()->format('Y-m-d');
        
        // 1. Gather report metrics
        $metrics = $this->gatherMetrics();

        // 2. Render PDF using dompdf
        $pdfPath = 'reports/report_' . $uuid . '.pdf';
        
        // Ensure directory exists inside storage
        if (!file_exists(storage_path('app/reports'))) {
            mkdir(storage_path('app/reports'), 0755, true);
        }

        $pdf = Pdf::loadView('pdf.analytics_report', [
            'title' => $title,
            'period' => $this->period,
            'generated_at' => Carbon::now()->format('Y-m-d H:i:s'),
            'metrics' => $metrics,
        ]);

        $pdf->save(storage_path('app/' . $pdfPath));

        // 3. Save report details in DB
        $report = AnalyticsReport::create([
            'uuid' => $uuid,
            'title' => $title,
            'type' => $this->period,
            'filters' => [
                'period' => $this->period,
                'date' => Carbon::today()->toDateString(),
            ],
            'generated_by' => null, // null means system-generated
            'generated_at' => Carbon::now(),
            'file_path' => $pdfPath,
        ]);

        // 4. Send email notifications to all admins
        $admins = User::whereHas('roles', function ($query) {
            $query->whereIn('slug', ['super-admin', 'admin', 'director']);
        })->get();

        foreach ($admins as $admin) {
            $admin->notify(new SendAnalyticsReportNotification($report));
        }
    }

    private function gatherMetrics(): array
    {
        $days = $this->period === 'monthly' ? 30 : ($this->period === 'weekly' ? 7 : 1);
        $startDate = Carbon::today()->subDays($days - 1)->startOfDay();
        $endDate = Carbon::today()->endOfDay();

        // Query metrics inside this period
        $metricModels = AnalyticsMetric::whereBetween('recorded_at', [$startDate, $endDate])
            ->orderBy('recorded_at', 'desc')
            ->get();

        $metrics = [];
        foreach ($metricModels as $m) {
            $metrics[$m->metric_key][] = [
                'value' => $m->metric_value,
                'date' => $m->recorded_at->format('Y-m-d'),
            ];
        }

        // Aggregate counts
        $finalMetrics = [];
        foreach ($metrics as $key => $values) {
            $sum = array_sum(array_column($values, 'value'));
            $avg = count($values) > 0 ? $sum / count($values) : 0;
            $finalMetrics[$key] = [
                'total' => $sum,
                'avg' => $avg,
                'history' => $values,
            ];
        }

        // Fill in missing default values
        $keys = [
            'website_visitors_unique_daily',
            'website_page_views_daily',
            'academy_active_learners_daily',
            'academy_course_completion_rate',
            'academy_certificates_issued',
            'events_registrations_rate',
        ];

        foreach ($keys as $k) {
            if (!isset($finalMetrics[$k])) {
                $finalMetrics[$k] = [
                    'total' => 0,
                    'avg' => 0,
                    'history' => [],
                ];
            }
        }

        return $finalMetrics;
    }
}
