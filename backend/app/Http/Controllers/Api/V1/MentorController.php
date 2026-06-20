<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\DiscussionThread;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Services\MentorAssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MentorController extends Controller
{
    public function __construct(
        protected MentorAssignmentService $mentorAssignmentService
    ) {}

    public function dashboard(Request $request): JsonResponse
    {
        $mentor = $request->user();

        if (!$mentor->hasRole('mentor') && !$mentor->hasRole('admin') && !$mentor->hasRole('super-admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Mentor access required.',
            ], 403);
        }

        $profile = $this->mentorAssignmentService->getMentorProfile($mentor);
        $studentIds = collect($profile['students'])->pluck('id');

        $courseQuery = Course::with(['modules.lessons', 'quizzes']);
        if ($mentor->hasRole('admin') || $mentor->hasRole('super-admin')) {
            $courseQuery->where('status', 'published');
        } else {
            $courseQuery->where('created_by', $mentor->id);
        }

        $courses = $courseQuery->get()
            ->map(function (Course $course) use ($mentor) {
                return [
                    'id' => $course->id,
                    'title' => $course->title,
                    'slug' => $course->slug,
                    'category' => $course->difficulty ?? 'General',
                    'description' => $course->description,
                    'mentorId' => $mentor->id,
                    'mentorName' => $mentor->name,
                    'mentorAvatar' => $mentor->avatar ?? '',
                    'published' => $course->status === 'published',
                    'enrollmentCount' => $course->enrollments()->count(),
                    'completionRate' => 0,
                    'quizAverage' => 0,
                    'xpReward' => 0,
                    'modulesCount' => $course->modules->count(),
                    'lessons' => $course->modules->flatMap(fn ($module) => $module->lessons)->values()->map(fn ($lesson) => [
                        'id' => $lesson->id,
                        'title' => $lesson->title,
                        'duration' => (string) ($lesson->estimated_duration ?? 30),
                        'order' => $lesson->order,
                        'contentType' => $lesson->video_url ? 'video' : 'text',
                        'published' => true,
                    ]),
                    'createdAt' => $course->created_at->toIso8601String(),
                ];
            });

        $volunteers = collect($profile['students'])->map(function ($student) {
            return [
                'id' => $student['id'],
                'name' => $student['name'],
                'email' => $student['email'],
                'avatar' => '',
                'enrolledCourses' => $student['enrollments_count'],
                'completedCourses' => $student['completed_count'],
                'quizAverage' => 0,
                'activeStreak' => 0,
                'lastActive' => isset($student['assigned_at'])
                    ? ($student['assigned_at'] instanceof \DateTimeInterface
                        ? $student['assigned_at']->format(\DateTimeInterface::ATOM)
                        : (string) $student['assigned_at'])
                    : now()->toIso8601String(),
                'progressPercent' => 0,
            ];
        });

        $quizAnalytics = Quiz::with('course')
            ->get()
            ->map(function (Quiz $quiz) use ($studentIds) {
                $attemptQuery = QuizAttempt::where('quiz_id', $quiz->id);
                if ($studentIds->isNotEmpty()) {
                    $attemptQuery->whereIn('user_id', $studentIds);
                }

                $attempts = $attemptQuery->get();
                $passed = $attempts->where('passed', true)->count();

                return [
                    'id' => $quiz->id,
                    'title' => $quiz->title,
                    'category' => $quiz->course?->difficulty ?? 'General',
                    'courseTitle' => $quiz->course?->title ?? 'Course',
                    'passingScore' => $quiz->passing_score,
                    'timeLimit' => $quiz->time_limit,
                    'totalAttempts' => $attempts->count(),
                    'uniqueVolunteers' => $attempts->pluck('user_id')->unique()->count(),
                    'averageScore' => (int) round($attempts->avg('score') ?? 0),
                    'passRate' => $attempts->count() > 0 ? (int) round(($passed / $attempts->count()) * 100) : 0,
                    'status' => 'Active',
                    'questionsAnalysis' => [],
                ];
            })
            ->filter(fn ($quiz) => $quiz['totalAttempts'] > 0)
            ->values();

        $submissions = QuizAttempt::with(['user', 'quiz.course'])
            ->when($studentIds->isNotEmpty(), fn ($q) => $q->whereIn('user_id', $studentIds))
            ->orderByDesc('submitted_at')
            ->limit(25)
            ->get()
            ->map(function (QuizAttempt $attempt) {
                $needsReview = !$attempt->passed;

                return [
                    'id' => $attempt->id,
                    'type' => 'reflection',
                    'volunteerName' => $attempt->user?->name ?? 'Student',
                    'volunteerAvatar' => $attempt->user?->avatar ?? '',
                    'itemTitle' => $attempt->quiz?->title ?? 'Quiz Attempt',
                    'courseTitle' => $attempt->quiz?->course?->title ?? 'Course',
                    'submittedAt' => $attempt->submitted_at?->toIso8601String() ?? $attempt->created_at->toIso8601String(),
                    'score' => $attempt->score,
                    'status' => $needsReview ? 'needs_review' : 'graded',
                    'details' => 'Quiz score ' . $attempt->score . '% — ' . ($attempt->passed ? 'passed' : 'needs mentor review'),
                ];
            });

        $discussions = DiscussionThread::with(['author', 'category'])
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn ($thread) => [
                'id' => $thread->id,
                'title' => $thread->title,
                'author' => $thread->author?->name,
                'category' => $thread->category?->name,
                'postsCount' => $thread->posts()->count(),
                'createdAt' => $thread->created_at->toIso8601String(),
            ]);

        return response()->json([
            'success' => true,
            'courses' => $courses,
            'submissions' => $submissions,
            'volunteers' => $volunteers,
            'quizAnalytics' => $quizAnalytics,
            'certifications' => [],
            'discussions' => $discussions,
        ]);
    }

    public function gradeSubmission(Request $request, QuizAttempt $attempt): JsonResponse
    {
        $mentor = $request->user();
        if (!$mentor->hasRole('mentor') && !$mentor->hasRole('admin') && !$mentor->hasRole('super-admin')) {
            return response()->json(['success' => false, 'message' => 'Mentor access required.'], 403);
        }

        $validated = $request->validate([
            'score' => 'required|integer|min:0|max:100',
            'passed' => 'sometimes|boolean',
        ]);

        $attempt->update([
            'score' => $validated['score'],
            'passed' => $validated['passed'] ?? ($validated['score'] >= ($attempt->quiz?->passing_score ?? 70)),
        ]);

        return response()->json([
            'success' => true,
            'submission' => [
                'id' => $attempt->id,
                'score' => $attempt->score,
                'status' => 'graded',
            ],
        ]);
    }
}
