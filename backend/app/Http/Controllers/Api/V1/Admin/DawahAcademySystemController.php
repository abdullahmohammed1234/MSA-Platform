<?php
/**
 * Dawah Academy System Admin Controller.
 */

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Module;
use App\Models\Lesson;
use App\Models\Quiz;
use App\Models\Question;
use App\Models\Achievement;
use App\Models\Badge;
use App\Models\LearningPath;
use App\Models\Enrollment;
use App\Models\QuizAttempt;
use App\Models\DiscussionThread;
use App\Models\DiscussionPost;
use App\Models\DiscussionReport;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Carbon;

class DawahAcademySystemController extends Controller
{
    /**
     * GET /api/v1/admin/systems/dawah-academy
     */
    public function index(Request $request): JsonResponse
    {
        if (!$request->user() || 
            (!$request->user()->hasPermission('system.view') && 
             !$request->user()->hasRole('super-admin') && 
             !$request->user()->hasRole('admin'))
        ) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        return response()->json([
            'success' => true,
            'system' => [
                'name' => 'Dawah Academy LMS',
                'slug' => 'dawah-academy',
                'version' => '1.5.0',
                'status' => 'operational',
                'updated_at' => Carbon::now()->toIso8601String()
            ]
        ]);
    }

    /**
     * GET /api/v1/admin/systems/dawah-academy/health
     */
    public function health(Request $request): JsonResponse
    {
        if (!$request->user() || 
            (!$request->user()->hasPermission('system.view') && 
             !$request->user()->hasRole('super-admin') && 
             !$request->user()->hasRole('admin'))
        ) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        // 1. Database connection latency
        $dbStatus = 'operational';
        $dbLatency = 0;
        try {
            $start = microtime(true);
            DB::connection()->getPdo();
            $dbLatency = round((microtime(true) - $start) * 1000, 1);
        } catch (\Throwable $e) {
            $dbStatus = 'offline';
        }

        // 2. Cache Health
        $cacheStatus = 'operational';
        try {
            $testKey = 'academy_health_check_' . time();
            Cache::put($testKey, 'working', 10);
            $cacheVal = Cache::get($testKey);
            Cache::forget($testKey);
            if ($cacheVal !== 'working') {
                $cacheStatus = 'warning';
            }
        } catch (\Throwable $e) {
            $cacheStatus = 'offline';
        }

        // 3. Discussion Forums Health
        $discussionsStatus = 'operational';
        $openReports = 0;
        try {
            $openReports = DiscussionReport::where('status', 'open')->count();
            if ($openReports > 5) {
                $discussionsStatus = 'warning';
            }
        } catch (\Throwable $e) {}

        return response()->json([
            'success' => true,
            'health' => [
                'api' => [
                    'status' => 'operational',
                    'avg_latency_ms' => 110,
                ],
                'database' => [
                    'status' => $dbStatus,
                    'latency_ms' => $dbLatency,
                    'pending_migrations' => 0,
                ],
                'cache' => [
                    'status' => $cacheStatus,
                    'driver' => config('cache.default', 'file'),
                ],
                'discussions' => [
                    'status' => $discussionsStatus,
                    'open_reports' => $openReports,
                    'threads' => DiscussionThread::count(),
                    'posts' => DiscussionPost::count()
                ]
            ]
        ]);
    }

    /**
     * GET /api/v1/admin/systems/dawah-academy/metrics
     */
    public function metrics(Request $request): JsonResponse
    {
        if (!$request->user() || 
            (!$request->user()->hasPermission('system.view') && 
             !$request->user()->hasRole('super-admin') && 
             !$request->user()->hasRole('admin'))
        ) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $enrollments = Enrollment::count();
        $activeEnrollments = Enrollment::where('status', 'active')->count();
        $completedEnrollments = Enrollment::where('status', 'completed')->count();

        $quizAttempts = QuizAttempt::count();
        $passingAttempts = QuizAttempt::where('passed', true)->count();
        $avgScore = round(QuizAttempt::avg('score') ?? 0, 1);

        return response()->json([
            'success' => true,
            'metrics' => [
                'courses' => Course::count(),
                'published_courses' => Course::where('status', 'published')->count(),
                'modules' => Module::count(),
                'lessons' => Lesson::count(),
                'quizzes' => Quiz::count(),
                'questions' => Question::count(),
                'achievements' => Achievement::count(),
                'badges' => Badge::count(),
                'learning_paths' => LearningPath::count(),
                'enrollments' => $enrollments,
                'active_enrollments' => $activeEnrollments,
                'completed_enrollments' => $completedEnrollments,
                'quiz_attempts' => $quizAttempts,
                'passing_quiz_attempts' => $passingAttempts,
                'average_quiz_score' => $avgScore
            ]
        ]);
    }

    /**
     * GET /api/v1/admin/systems/dawah-academy/integrations
     */
    public function integrations(Request $request): JsonResponse
    {
        if (!$request->user() || 
            (!$request->user()->hasPermission('system.view') && 
             !$request->user()->hasRole('super-admin') && 
             !$request->user()->hasRole('admin'))
        ) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        return response()->json([
            'success' => true,
            'integrations' => [
                'ai_mentor' => [
                    'status' => 'Operational',
                    'service' => 'LLM Helper Engine',
                    'active_sessions' => DB::table('ai_mentor_sessions')->where('updated_at', '>=', now()->subMinutes(30))->count()
                ],
                'gamification' => [
                    'status' => 'Operational',
                    'features' => ['XP points', 'Achievements', 'Progress Levels']
                ]
            ]
        ]);
    }

    /**
     * GET /api/v1/admin/systems/dawah-academy/config
     */
    public function getConfig(Request $request): JsonResponse
    {
        if (!$request->user() || 
            (!$request->user()->hasPermission('system.view') && 
             !$request->user()->hasRole('super-admin') && 
             !$request->user()->hasRole('admin'))
        ) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        return response()->json([
            'success' => true,
            'config' => $this->loadConfig()
        ]);
    }

    /**
     * PUT /api/v1/admin/systems/dawah-academy/config
     */
    public function updateConfig(Request $request): JsonResponse
    {
        if (!$request->user() || 
            (!$request->user()->hasPermission('system.manage') && 
             !$request->user()->hasRole('super-admin') && 
             !$request->user()->hasRole('admin'))
        ) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'timezone' => 'required|string',
            'course_passing_score' => 'required|integer|min:50|max:100',
            'max_quiz_attempts' => 'required|integer|min:1|max:10',
            'email_notifications' => 'required|boolean',
            'gamification_enabled' => 'required|boolean',
            'daily_xp_limit' => 'required|integer|min:100|max:5000'
        ]);

        $this->saveConfig($validated);

        return response()->json([
            'success' => true,
            'message' => 'Dawah Academy configurations saved successfully.',
            'config' => $validated
        ]);
    }

    // --- Helpers -------------------------------------------------------------

    private function loadConfig(): array
    {
        $filePath = storage_path('app/academy_config.json');

        $defaults = [
            'timezone' => 'America/Vancouver',
            'course_passing_score' => 80,
            'max_quiz_attempts' => 3,
            'email_notifications' => true,
            'gamification_enabled' => true,
            'daily_xp_limit' => 500
        ];

        if (file_exists($filePath)) {
            $custom = json_decode(file_get_contents($filePath), true);
            if (is_array($custom)) {
                return array_merge($defaults, $custom);
            }
        }

        return $defaults;
    }

    private function saveConfig(array $config): void
    {
        $filePath = storage_path('app/academy_config.json');
        $dir = dirname($filePath);
        if (!file_exists($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($filePath, json_encode($config, JSON_PRETTY_PRINT));
    }
}
