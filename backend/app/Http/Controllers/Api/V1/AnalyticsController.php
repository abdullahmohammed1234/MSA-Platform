<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AnalyticsEvent;
use App\Models\AnalyticsSession;
use App\Models\AnalyticsMetric;
use App\Models\AnalyticsReport;
use App\Models\CertificateAward;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\Analytics\AnalyticsService;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;

use Illuminate\Support\Facades\Cache;

class AnalyticsController extends Controller
{
    protected AnalyticsService $analyticsService;

    public function __construct(AnalyticsService $analyticsService)
    {
        $this->analyticsService = $analyticsService;
    }

    /**
     * POST /api/v1/analytics/session
     * Sync frontend tracking session details.
     */
    public function syncSession(Request $request)
    {
        $validated = $request->validate([
            'session_uuid' => 'required|uuid',
            'device' => 'nullable|string',
            'browser' => 'nullable|string',
            'platform' => 'nullable|string',
            'referrer' => 'nullable|string',
        ]);

        $userId = $request->user() ? $request->user()->id : null;

        $session = $this->analyticsService->syncSession(
            $validated['session_uuid'],
            $userId,
            $validated
        );

        return response()->json([
            'success' => true,
            'session' => $session,
        ]);
    }

    /**
     * POST /api/v1/analytics/track
     * Track event from frontend client.
     */
    public function track(Request $request)
    {
        $validated = $request->validate([
            'session_uuid' => 'nullable|uuid',
            'module' => 'required|string|in:website,academy,events,certificates',
            'event_type' => 'required|string|in:page_view,click,submission,award,conversion',
            'event_name' => 'required|string',
            'entity_type' => 'nullable|string',
            'entity_id' => 'nullable|integer',
            'metadata' => 'nullable|array',
            'occurred_at' => 'nullable|date',
        ]);

        $validated['user_id'] = $request->user() ? $request->user()->id : null;

        $this->analyticsService->track($validated);

        return response()->json(['success' => true]);
    }

    /**
     * GET /api/v1/analytics/overview
     */
    public function overview(Request $request)
    {
        if (!$request->user() || !$request->user()->canViewAnalytics()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $range = $this->getDateRange($request);
        $start = $range['start'];
        $end = $range['end'];

        $cacheKey = 'analytics_overview_' . md5($start->toDateTimeString() . '_' . $end->toDateTimeString());

        $data = Cache::remember($cacheKey, 300, function () use ($start, $end) {
            // Core KPIs
            $visitors = AnalyticsSession::whereBetween('started_at', [$start, $end])->count();
            
            $pageViews = AnalyticsEvent::where('event_name', 'page_view')
                ->whereBetween('occurred_at', [$start, $end])
                ->count();

            $activeLearners = AnalyticsEvent::whereIn('event_name', ['lesson_completed', 'quiz_submitted', 'course_completed'])
                ->whereBetween('occurred_at', [$start, $end])
                ->whereNotNull('user_id')
                ->distinct('user_id')
                ->count();

            $certificates = CertificateAward::whereBetween('issued_at', [$start, $end])->count();

            // Calculate trends (comparing to previous period)
            $diff = $start->diffInDays($end) + 1;
            $prevStart = $start->copy()->subDays($diff);
            $prevEnd = $start->copy()->subMicrosecond();

            $prevVisitors = AnalyticsSession::whereBetween('started_at', [$prevStart, $prevEnd])->count();
            $prevPageViews = AnalyticsEvent::where('event_name', 'page_view')
                ->whereBetween('occurred_at', [$prevStart, $prevEnd])
                ->count();
            $prevActiveLearners = AnalyticsEvent::whereIn('event_name', ['lesson_completed', 'quiz_submitted', 'course_completed'])
                ->whereBetween('occurred_at', [$prevStart, $prevEnd])
                ->whereNotNull('user_id')
                ->distinct('user_id')
                ->count();
            $prevCertificates = CertificateAward::whereBetween('issued_at', [$prevStart, $prevEnd])->count();

            // Recent Activity Feed
            $recentCompletions = Enrollment::with('user', 'course')
                ->where('status', 'completed')
                ->orderBy('completed_at', 'desc')
                ->limit(5)
                ->get()
                ->map(fn($e) => [
                    'type' => 'completion',
                    'user' => $e->user->name ?? 'Anonymous',
                    'detail' => $e->course->title ?? 'Course',
                    'time' => $e->completed_at ? $e->completed_at->diffForHumans() : 'Recently',
                ]);

            $recentCertificates = CertificateAward::with('user')
                ->orderBy('issued_at', 'desc')
                ->limit(5)
                ->get()
                ->map(fn($c) => [
                    'type' => 'certificate',
                    'user' => $c->user->name ?? 'Anonymous',
                    'detail' => $c->title,
                    'time' => $c->issued_at ? $c->issued_at->diffForHumans() : 'Recently',
                ]);

            $recentRegistrations = AnalyticsEvent::where('event_name', 'event_registered')
                ->orderBy('occurred_at', 'desc')
                ->limit(5)
                ->get()
                ->map(function($ev) {
                    $user = $ev->user ? $ev->user->name : 'Anonymous Guest';
                    $eventName = $ev->metadata['event_title'] ?? 'Public Event';
                    return [
                        'type' => 'registration',
                        'user' => $user,
                        'detail' => $eventName,
                        'time' => $ev->occurred_at ? $ev->occurred_at->diffForHumans() : 'Recently',
                    ];
                });

            $recentActivity = $recentCompletions->concat($recentCertificates)->concat($recentRegistrations)
                ->sortByDesc(fn($x) => $x['time'])
                ->values()
                ->take(8);

            return [
                'kpis' => [
                    'visitors' => ['value' => $visitors, 'change' => $this->percentChange($visitors, $prevVisitors)],
                    'page_views' => ['value' => $pageViews, 'change' => $this->percentChange($pageViews, $prevPageViews)],
                    'active_learners' => ['value' => $activeLearners, 'change' => $this->percentChange($activeLearners, $prevActiveLearners)],
                    'certificates' => ['value' => $certificates, 'change' => $this->percentChange($certificates, $prevCertificates)],
                ],
                'recent_activity' => $recentActivity,
            ];
        });

        return response()->json(array_merge(['success' => true], $data));
    }

    /**
     * GET /api/v1/analytics/website
     */
    public function website(Request $request)
    {
        if (!$request->user() || !$request->user()->canViewAnalytics()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $range = $this->getDateRange($request);
        $start = $range['start'];
        $end = $range['end'];

        $cacheKey = 'analytics_website_' . md5($start->toDateTimeString() . '_' . $end->toDateTimeString());

        $data = Cache::remember($cacheKey, 300, function () use ($start, $end) {
            // Visitors over time
            $visitorsOverTime = AnalyticsSession::select(DB::raw('DATE(started_at) as date'), DB::raw('COUNT(*) as count'))
                ->whereBetween('started_at', [$start, $end])
                ->groupBy(DB::raw('DATE(started_at)'))
                ->get();

            // Page view breakdown
            $popularPages = AnalyticsEvent::where('event_name', 'page_view')
                ->whereBetween('occurred_at', [$start, $end])
                ->get()
                ->groupBy(function($event) {
                    return $event->metadata['url'] ?? '/';
                })
                ->map(function($events, $url) {
                    return [
                        'url' => $url,
                        'title' => $events->first()->metadata['title'] ?? $url,
                        'views' => $events->count(),
                        'unique_views' => $events->pluck('session_id')->unique()->count(),
                    ];
                })
                ->sortByDesc('views')
                ->values()
                ->take(8);

            // Traffic sources
            $trafficSources = AnalyticsSession::select('referrer', DB::raw('COUNT(*) as count'))
                ->whereBetween('started_at', [$start, $end])
                ->groupBy('referrer')
                ->get()
                ->map(function($s) {
                    $source = 'Direct';
                    if ($s->referrer) {
                        $host = parse_url($s->referrer, PHP_URL_HOST);
                        if ($host) {
                            if (str_contains($host, 'google') || str_contains($host, 'bing')) {
                                $source = 'Search Engines';
                            } elseif (str_contains($host, 'facebook') || str_contains($host, 'twitter') || str_contains($host, 'instagram') || str_contains($host, 'linkedin')) {
                                $source = 'Social Media';
                            } else {
                                $source = $host;
                            }
                        }
                    }
                    return ['source' => $source, 'count' => $s->count];
                })
                ->groupBy('source')
                ->map(fn($group) => $group->sum('count'))
                ->map(fn($count, $source) => ['source' => $source, 'count' => $count])
                ->values();

            // CTA Click conversions
            $ctaClicks = AnalyticsEvent::where('event_name', 'cta_click')
                ->whereBetween('occurred_at', [$start, $end])
                ->get()
                ->groupBy(fn($ev) => $ev->metadata['cta_name'] ?? 'general')
                ->map(fn($events, $name) => [
                    'name' => ucfirst($name),
                    'clicks' => $events->count(),
                ])
                ->values();

            return [
                'visitors_over_time' => $visitorsOverTime,
                'popular_pages' => $popularPages,
                'traffic_sources' => $trafficSources,
                'cta_performance' => $ctaClicks,
            ];
        });

        return response()->json(array_merge(['success' => true], $data));
    }

    /**
     * GET /api/v1/analytics/academy
     */
    public function academy(Request $request)
    {
        if (!$request->user() || !$request->user()->canViewAnalytics()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $range = $this->getDateRange($request);
        $start = $range['start'];
        $end = $range['end'];

        $cacheKey = 'analytics_academy_' . md5($start->toDateTimeString() . '_' . $end->toDateTimeString());

        $data = Cache::remember($cacheKey, 300, function () use ($start, $end) {
            // Course completion stats per course
            $courses = Course::withCount([
                'enrollments as total_enrollments',
                'enrollments as completed_enrollments' => fn($query) => $query->where('status', 'completed')
            ])->get()->map(function($c) {
                $completionRate = $c->total_enrollments > 0 ? ($c->completed_enrollments / $c->total_enrollments) * 100 : 0;
                return [
                    'course_id' => $c->id,
                    'title' => $c->title,
                    'total' => $c->total_enrollments,
                    'completed' => $c->completed_enrollments,
                    'completion_rate' => round($completionRate, 1),
                ];
            });

            // Quiz stats
            $quizzes = DB::table('quizzes')
                ->join('quiz_attempts', 'quizzes.id', '=', 'quiz_attempts.quiz_id')
                ->select('quizzes.id', 'quizzes.title', DB::raw('AVG(quiz_attempts.score) as avg_score'), DB::raw('SUM(CASE WHEN quiz_attempts.passed = 1 THEN 1 ELSE 0 END) as passed_count'), DB::raw('COUNT(quiz_attempts.id) as total_attempts'))
                ->whereBetween('quiz_attempts.created_at', [$start, $end])
                ->groupBy('quizzes.id', 'quizzes.title')
                ->get()
                ->map(fn($q) => [
                    'quiz_id' => $q->id,
                    'title' => $q->title,
                    'average_score' => round($q->avg_score, 1),
                    'passing_rate' => $q->total_attempts > 0 ? round(($q->passed_count / $q->total_attempts) * 100, 1) : 0,
                ]);

            // Volunteer Engagement metrics
            $topVolunteers = User::whereHas('roles', fn($q) => $q->where('slug', 'volunteer'))
                ->withCount('certificateAwards')
                ->limit(5)
                ->get()
                ->map(fn($u) => [
                    'name' => $u->name,
                    'certificates' => $u->certificate_awards_count,
                ])
                ->sortByDesc('certificates')
                ->values();

            return [
                'summary' => [
                    'courses_count' => Course::count(),
                    'enrolled_users_count' => Enrollment::distinct('user_id')->count(),
                    'enrollments' => [
                        'active' => Enrollment::where('status', 'active')->count(),
                        'completed' => Enrollment::where('status', 'completed')->count(),
                        'dropped' => Enrollment::where('status', 'dropped')->count(),
                    ],
                    'course_performance' => $courses,
                    'quiz_performance' => $quizzes,
                ],
                'top_volunteers' => $topVolunteers,
            ];
        });

        return response()->json(array_merge(['success' => true], $data));
    }

    /**
     * GET /api/v1/analytics/events
     */
    public function events(Request $request)
    {
        if (!$request->user() || !$request->user()->canViewAnalytics()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $range = $this->getDateRange($request);
        $start = $range['start'];
        $end = $range['end'];

        $cacheKey = 'analytics_events_' . md5($start->toDateTimeString() . '_' . $end->toDateTimeString());

        $data = Cache::remember($cacheKey, 300, function () use ($start, $end) {
            $popularEvents = DB::table('analytics_events')
                ->select('entity_id', DB::raw('COUNT(*) as registrations_count'))
                ->where('event_name', 'event_registered')
                ->whereBetween('occurred_at', [$start, $end])
                ->groupBy('entity_id')
                ->orderByDesc('registrations_count')
                ->limit(5)
                ->get()
                ->map(function($ev) {
                    $title = DB::table('cms_events')->where('id', $ev->entity_id)->value('title') ?? 'Event #' . $ev->entity_id;
                    return [
                        'event_id' => $ev->entity_id,
                        'title' => $title,
                        'registrations' => $ev->registrations_count,
                    ];
                });

            return [
                'popular_events' => $popularEvents,
            ];
        });

        return response()->json(array_merge(['success' => true], $data));
    }

    /**
     * GET /api/v1/analytics/reports
     */
    public function reports(Request $request)
    {
        if (!$request->user() || !$request->user()->canViewReports()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $reports = AnalyticsReport::with('generatedBy')
            ->orderBy('generated_at', 'desc')
            ->paginate(10);

        return response()->json([
            'success' => true,
            'reports' => $reports,
        ]);
    }

    /**
     * GET /api/v1/analytics/export
     */
    public function export(Request $request)
    {
        if (!$request->user() || !$request->user()->canExportAnalytics()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'format' => 'required|in:csv,pdf',
            'type' => 'required|in:website,academy,events,overview',
        ]);

        $range = $this->getDateRange($request);
        $start = $range['start'];
        $end = $range['end'];

        if ($validated['format'] === 'csv') {
            return $this->exportCSV($validated['type'], $start, $end);
        }

        return $this->exportPDF($validated['type'], $start, $end);
    }

    private function exportCSV(string $type, Carbon $start, Carbon $end)
    {
        $filename = "analytics_{$type}_" . now()->format('Y-m-d') . ".csv";

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($type, $start, $end) {
            $file = fopen('php://output', 'w');

            if ($type === 'website') {
                fputcsv($file, ['Date', 'URL', 'Event Name', 'User ID', 'Browser', 'Referrer']);
                AnalyticsEvent::with('session')
                    ->where('module', 'website')
                    ->whereBetween('occurred_at', [$start, $end])
                    ->chunk(500, function ($events) use ($file) {
                        foreach ($events as $e) {
                            fputcsv($file, [
                                $e->occurred_at,
                                $e->metadata['url'] ?? '/',
                                $e->event_name,
                                $e->user_id,
                                $e->session->browser ?? 'Unknown',
                                $e->session->referrer ?? 'Direct',
                            ]);
                        }
                    });
            } elseif ($type === 'academy') {
                fputcsv($file, ['Enrollment Date', 'User Name', 'Course Title', 'Status', 'Completed Date']);
                Enrollment::with('user', 'course')
                    ->whereBetween('created_at', [$start, $end])
                    ->chunk(500, function ($enrollments) use ($file) {
                        foreach ($enrollments as $e) {
                            fputcsv($file, [
                                $e->created_at,
                                $e->user->name ?? 'Deleted User',
                                $e->course->title ?? 'Deleted Course',
                                $e->status,
                                $e->completed_at,
                            ]);
                        }
                    });
            } else {
                fputcsv($file, ['Event Date', 'Event Type', 'Event Name', 'User ID', 'Module']);
                AnalyticsEvent::whereBetween('occurred_at', [$start, $end])
                    ->chunk(500, function ($events) use ($file) {
                        foreach ($events as $e) {
                            fputcsv($file, [
                                $e->occurred_at,
                                $e->event_type,
                                $e->event_name,
                                $e->user_id,
                                $e->module,
                            ]);
                        }
                    });
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function exportPDF(string $type, Carbon $start, Carbon $end)
    {
        $title = ucfirst($type) . " Analytics Report";
        
        $metrics = [
            'website_visitors_unique_daily' => ['total' => AnalyticsSession::whereBetween('started_at', [$start, $end])->count(), 'avg' => 0],
            'website_page_views_daily' => ['total' => AnalyticsEvent::where('event_name', 'page_view')->whereBetween('occurred_at', [$start, $end])->count(), 'avg' => 0],
            'academy_active_learners_daily' => ['total' => 0, 'avg' => 0],
            'academy_course_completion_rate' => ['total' => 0, 'avg' => Enrollment::count() > 0 ? (Enrollment::where('status', 'completed')->count() / Enrollment::count()) * 100 : 0],
            'academy_certificates_issued' => ['total' => CertificateAward::count(), 'avg' => 0],
            'events_registrations_rate' => ['total' => 0, 'avg' => 0],
        ];

        $pdf = Pdf::loadView('pdf.analytics_report', [
            'title' => $title,
            'period' => 'custom',
            'generated_at' => Carbon::now()->format('Y-m-d H:i:s'),
            'metrics' => $metrics,
        ]);

        return $pdf->download("analytics_{$type}_" . now()->format('Y-m-d') . ".pdf");
    }

    private function getDateRange(Request $request): array
    {
        $startStr = $request->query('start_date');
        $endStr = $request->query('end_date');

        $start = $startStr ? Carbon::parse($startStr)->startOfDay() : Carbon::now()->subDays(30)->startOfDay();
        $end = $endStr ? Carbon::parse($endStr)->endOfDay() : Carbon::now()->endOfDay();

        return ['start' => $start, 'end' => $end];
    }

    private function percentChange(float $current, float $previous): float
    {
        if ($previous == 0) {
            return $current > 0 ? 100 : 0;
        }
        return round((($current - $previous) / $previous) * 100, 1);
    }
}
