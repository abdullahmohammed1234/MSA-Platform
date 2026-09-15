<?php

namespace App\Services\Intelligence;

use App\Ems\Models\EventFeedback;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FeedbackIntelligenceService
{
    private EmsIntelligenceService $helper;

    public function __construct(EmsIntelligenceService $helper)
    {
        $this->helper = $helper;
    }

    public function getAnalytics(string $period = '30d', ?string $startDate = null, ?string $endDate = null): array
    {
        $bounds = $this->helper->resolveDateBounds($period, $startDate, $endDate);
        $cStart = $bounds['current']['start'];
        $cEnd = $bounds['current']['end'];
        $pStart = $bounds['previous']['start'];
        $pEnd = $bounds['previous']['end'];

        if (! Schema::hasTable('ems_feedback_responses') && ! Schema::hasTable('event_feedback')) {
            return $this->emptyFeedbackAnalytics();
        }

        $table = Schema::hasTable('ems_feedback_responses') ? 'ems_feedback_responses' : 'event_feedback';

        $feedbackCount = DB::table($table)->whereBetween('created_at', [$cStart, $cEnd])->count();
        $avgOverall = (float) (DB::table($table)->whereBetween('created_at', [$cStart, $cEnd])->avg('overall_rating') ?? 0.0);
        $avgProgram = (float) (DB::table($table)->whereBetween('created_at', [$cStart, $cEnd])->avg('program_rating') ?? 0.0);
        $avgOrganization = (float) (DB::table($table)->whereBetween('created_at', [$cStart, $cEnd])->avg('organization_rating') ?? 0.0);
        $avgVenue = (float) (DB::table($table)->whereBetween('created_at', [$cStart, $cEnd])->avg('venue_rating') ?? 0.0);

        // Rating distribution (1 to 5 stars)
        $distribution = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
        $distRows = DB::table($table)
            ->whereBetween('created_at', [$cStart, $cEnd])
            ->select('overall_rating', DB::raw('count(*) as count'))
            ->groupBy('overall_rating')
            ->get();

        foreach ($distRows as $row) {
            $star = (int) $row->overall_rating;
            if (isset($distribution[$star])) {
                $distribution[$star] = (int) $row->count;
            }
        }

        // Trends
        $trends = [];
        $cursor = $cStart->copy();
        while ($cursor->lte($cEnd)) {
            $dayStart = $cursor->copy()->startOfDay();
            $dayEnd = $cursor->copy()->endOfDay();

            $cnt = DB::table($table)->whereBetween('created_at', [$dayStart, $dayEnd])->count();
            $avg = (float) (DB::table($table)->whereBetween('created_at', [$dayStart, $dayEnd])->avg('overall_rating') ?? 0.0);

            $trends[] = [
                'date' => $cursor->format('Y-m-d'),
                'label' => $cursor->format('M j'),
                'count' => $cnt,
                'average_rating' => round($avg, 2),
            ];

            $cursor->addDay();
        }

        return [
            'period' => [
                'type' => $period,
                'current_start' => $cStart->toIso8601String(),
                'current_end' => $cEnd->toIso8601String(),
            ],
            'kpis' => [
                'total_responses' => $feedbackCount,
                'average_overall' => round($avgOverall, 2),
                'average_program' => round($avgProgram, 2),
                'average_organization' => round($avgOrganization, 2),
                'average_venue' => round($avgVenue, 2),
            ],
            'distribution' => $distribution,
            'trends' => $trends,
        ];
    }

    private function emptyFeedbackAnalytics(): array
    {
        return [
            'period' => ['type' => '30d'],
            'kpis' => [
                'total_responses' => 0,
                'average_overall' => 0.0,
                'average_program' => 0.0,
                'average_organization' => 0.0,
                'average_venue' => 0.0,
            ],
            'distribution' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0],
            'trends' => [],
        ];
    }
}
