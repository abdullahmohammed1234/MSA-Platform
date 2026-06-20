<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\Progress;
use App\Models\QuizAttempt;
use App\Services\BadgeService;
use App\Services\CourseService;
use App\Services\EnrollmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AcademyDashboardController extends Controller
{
    public function __construct(
        protected EnrollmentService $enrollmentService,
        protected CourseService $courseService,
        protected BadgeService $badgeService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $enrollments = Enrollment::with('course.modules.lessons')
            ->where('user_id', $userId)
            ->get();

        $progressRows = Progress::with('lesson')
            ->where('user_id', $userId)
            ->where('completed', true)
            ->orderByDesc('completed_at')
            ->limit(20)
            ->get();

        $quizAttempts = QuizAttempt::with('quiz.course')
            ->where('user_id', $userId)
            ->orderByDesc('submitted_at')
            ->limit(10)
            ->get();

        $badges = $this->badgeService->getBadgesForUser($userId);
        $unlockedBadges = $badges->filter(fn ($b) => $b->awards->isNotEmpty())->count();

        $courseProgress = [];
        foreach ($enrollments as $enrollment) {
            $course = $enrollment->course;
            if (!$course) {
                continue;
            }
            $totalLessons = $course->modules->flatMap->lessons->count();
            $completed = Progress::where('user_id', $userId)
                ->where('course_id', $course->id)
                ->where('completed', true)
                ->count();
            $courseProgress[$course->id] = $totalLessons > 0
                ? (int) round(($completed / $totalLessons) * 100)
                : 0;
        }

        $enrolledCount = $enrollments->count();
        $completedCourses = collect($courseProgress)->filter(fn ($p) => $p >= 100)->count();
        $overallProgress = $enrolledCount > 0
            ? (int) round(collect($courseProgress)->avg())
            : 0;

        $totalXp = ($progressRows->count() * 25)
            + ($quizAttempts->where('passed', true)->count() * 100)
            + ($unlockedBadges * 50);

        $activityDates = $progressRows->pluck('completed_at')
            ->merge($quizAttempts->pluck('submitted_at'))
            ->filter()
            ->map(fn ($d) => $d->format('Y-m-d'))
            ->unique()
            ->sort()
            ->values();

        $streakDays = $this->calculateStreak($activityDates->all());

        $activities = collect();

        foreach ($progressRows->take(5) as $row) {
            $activities->push([
                'id' => 'progress-' . $row->id,
                'type' => 'lesson_completed',
                'title' => 'Lesson Completed',
                'detail' => 'Completed "' . ($row->lesson?->title ?? 'Lesson') . '".',
                'timestamp' => $row->completed_at?->toIso8601String() ?? $row->updated_at->toIso8601String(),
            ]);
        }

        foreach ($quizAttempts->take(5) as $attempt) {
            $activities->push([
                'id' => 'quiz-' . $attempt->id,
                'type' => $attempt->passed ? 'quiz_passed' : 'quiz_failed',
                'title' => $attempt->passed ? 'Quiz Passed' : 'Quiz Attempt',
                'detail' => ($attempt->quiz?->title ?? 'Quiz') . ' — ' . $attempt->score . '%',
                'timestamp' => $attempt->submitted_at?->toIso8601String() ?? $attempt->created_at->toIso8601String(),
            ]);
        }

        foreach ($enrollments->sortByDesc('enrolled_at')->take(3) as $enrollment) {
            $activities->push([
                'id' => 'enroll-' . $enrollment->id,
                'type' => 'enrolled',
                'title' => 'Enrolled in Course',
                'detail' => 'Enrolled in "' . ($enrollment->course?->title ?? 'Course') . '".',
                'timestamp' => $enrollment->enrolled_at?->toIso8601String() ?? $enrollment->created_at->toIso8601String(),
            ]);
        }

        $recentActivities = $activities
            ->sortByDesc('timestamp')
            ->values()
            ->take(8)
            ->all();

        $continueLearning = $this->resolveContinueLearning($userId, $enrollments, $courseProgress);

        $recommended = $this->courseService->getPublishedCourses([], 10);
        $recommendedCourses = collect($recommended->items())
            ->whereNotIn('id', $enrollments->pluck('course_id'))
            ->take(2)
            ->values()
            ->map(fn ($c) => [
                'id' => $c->id,
                'title' => $c->title,
                'slug' => $c->slug,
                'difficulty' => $c->difficulty,
            ]);

        return response()->json([
            'success' => true,
            'stats' => [
                'coursesEnrolled' => $enrolledCount,
                'coursesCompleted' => $completedCourses,
                'badgesUnlocked' => $unlockedBadges,
                'overallProgress' => $overallProgress,
                'totalXp' => $totalXp,
                'streakDays' => $streakDays,
            ],
            'courseProgress' => $courseProgress,
            'recentActivities' => $recentActivities,
            'continueLearning' => $continueLearning,
            'recommendedCourses' => $recommendedCourses,
        ]);
    }

    private function calculateStreak(array $dates): int
    {
        if (empty($dates)) {
            return 0;
        }

        $set = array_flip($dates);
        $streak = 0;
        $cursor = now()->startOfDay();

        while (isset($set[$cursor->format('Y-m-d')])) {
            $streak++;
            $cursor->subDay();
        }

        return $streak;
    }

    private function resolveContinueLearning(int $userId, $enrollments, array $courseProgress): ?array
    {
        foreach ($enrollments as $enrollment) {
            $course = $enrollment->course;
            if (!$course) {
                continue;
            }

            $pct = $courseProgress[$course->id] ?? 0;
            if ($pct >= 100) {
                continue;
            }

            foreach ($course->modules as $module) {
                foreach ($module->lessons as $lesson) {
                    $done = Progress::where('user_id', $userId)
                        ->where('course_id', $course->id)
                        ->where('lesson_id', $lesson->id)
                        ->where('completed', true)
                        ->exists();

                    if (!$done) {
                        return [
                            'courseId' => $course->id,
                            'courseTitle' => $course->title,
                            'courseSlug' => $course->slug,
                            'moduleTitle' => $module->title,
                            'lessonTitle' => $lesson->title,
                            'lessonId' => $lesson->id,
                            'progress' => $pct,
                        ];
                    }
                }
            }
        }

        $first = $enrollments->first()?->course;
        if (!$first) {
            return null;
        }

        $firstLesson = $first->modules->flatMap->lessons->first();

        return [
            'courseId' => $first->id,
            'courseTitle' => $first->title,
            'courseSlug' => $first->slug,
            'moduleTitle' => $first->modules->first()?->title ?? 'Module',
            'lessonTitle' => $firstLesson?->title ?? 'First Lesson',
            'lessonId' => $firstLesson?->id,
            'progress' => $courseProgress[$first->id] ?? 0,
        ];
    }
}
