<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academy\SubmitQuizRequest;
use App\Http\Resources\Academy\CourseResource;
use App\Http\Resources\Academy\EnrollmentResource;
use App\Http\Resources\Academy\ProgressResource;
use App\Http\Resources\Academy\QuizAttemptResource;
use App\Http\Resources\Academy\CertificateResource;
use App\Http\Resources\Academy\LearningPathResource;
use App\Http\Resources\Academy\AchievementResource;
use App\Http\Resources\Academy\BadgeResource;
use App\Http\Resources\Academy\MilestoneResource;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Certificate;
use App\Models\CMS\Resource as CmsResource;
use App\Repositories\QuizRepository;
use App\Services\CourseService;
use App\Services\EnrollmentService;
use App\Services\ProgressService;
use App\Services\QuizService;
use App\Services\CertificateService;
use App\Services\LearningPathService;
use App\Services\AchievementService;
use App\Services\BadgeService;
use App\Services\MilestoneService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\Cache;

class AcademyController extends Controller
{
    protected $courseService;
    protected $enrollmentService;
    protected $progressService;
    protected $quizService;
    protected $certificateService;
    protected $learningPathService;

    public function __construct(
        CourseService $courseService,
        EnrollmentService $enrollmentService,
        ProgressService $progressService,
        QuizService $quizService,
        CertificateService $certificateService,
        LearningPathService $learningPathService
    ) {
        $this->courseService = $courseService;
        $this->enrollmentService = $enrollmentService;
        $this->progressService = $progressService;
        $this->quizService = $quizService;
        $this->certificateService = $certificateService;
        $this->learningPathService = $learningPathService;
    }

    /**
     * Get list of published courses.
     */
    public function courses(Request $request): JsonResponse
    {
        $filters = $request->only(['difficulty', 'search']);
        $page = $request->get('page', 1);
        $perPage = $request->get('per_page', 15);

        $version = Cache::rememberForever('academy_courses_version', fn() => 1);
        $cacheKey = 'academy_courses_v' . $version . '_' . md5(serialize($filters) . "_{$page}_{$perPage}");

        $result = Cache::remember($cacheKey, 600, function () use ($filters, $perPage) {
            $courses = $this->courseService->getPublishedCourses($filters, $perPage);
            return [
                'courses' => CourseResource::collection($courses)->resolve(),
                'meta' => [
                    'current_page' => $courses->currentPage(),
                    'last_page' => $courses->lastPage(),
                    'total' => $courses->total(),
                ]
            ];
        });

        return response()->json([
            'success' => true,
            'courses' => $result['courses'],
            'meta' => $result['meta']
        ]);
    }

    /**
     * Get details of a single course.
     */
    public function courseDetails(string $idOrSlug): JsonResponse
    {
        $course = is_numeric($idOrSlug)
            ? Course::with(['creator', 'modules.lessons', 'quizzes'])->find($idOrSlug)
            : $this->courseService->getCourseBySlug($idOrSlug);

        if (!$course) {
            return response()->json(['success' => false, 'message' => 'Course not found.'], 404);
        }

        $this->authorize('view', $course);

        // Load progress for authenticated user
        $user = auth()->user();
        $progress = [];
        $enrollment = null;

        if ($user) {
            $enrollment = $this->enrollmentService->getEnrollment($user->id, $course->id);
            // Eager load progress if enrolled
            if ($enrollment) {
                $progress = $user->progressRecords()->where('course_id', $course->id)->get();
            }
        }

        return response()->json([
            'success' => true,
            'course' => new CourseResource($course),
            'enrollment' => $enrollment ? new EnrollmentResource($enrollment) : null,
            'progress' => ProgressResource::collection($progress),
        ]);
    }

    /**
     * Enroll in a course.
     */
    public function enroll(Request $request, int $courseId): JsonResponse
    {
        $course = Course::find($courseId);
        if (!$course || $course->status !== 'published') {
            return response()->json(['success' => false, 'message' => 'Course is not available.'], 404);
        }

        $enrollment = $this->enrollmentService->enroll($request->user()->id, $courseId);

        return response()->json([
            'success' => true,
            'message' => 'Enrolled successfully.',
            'enrollment' => new EnrollmentResource($enrollment),
        ], 201);
    }

    /**
     * Mark a lesson as completed.
     */
    public function completeLesson(Request $request, int $courseId, int $lessonId): JsonResponse
    {
        $lesson = Lesson::find($lessonId);
        if (!$lesson) {
            return response()->json(['success' => false, 'message' => 'Lesson not found.'], 404);
        }

        $userId = $request->user()->id;
        $enrollment = $this->enrollmentService->getEnrollment($userId, $courseId);
        if (!$enrollment) {
            return response()->json(['success' => false, 'message' => 'You must be enrolled in the course to complete lessons.'], 403);
        }

        $result = $this->progressService->completeLesson($userId, $courseId, $lessonId);

        return response()->json([
            'success' => true,
            'message' => 'Lesson completed successfully.',
            'completion_percentage' => $result['completion_percentage'],
            'course_completed' => $result['course_completed'],
            'progress' => new ProgressResource($result['progress']),
        ]);
    }

    /**
     * Submit answers for a quiz.
     */
    public function submitQuiz(SubmitQuizRequest $request, int $courseId, int $quizId): JsonResponse
    {
        $quiz = Quiz::find($quizId);
        if (!$quiz) {
            return response()->json(['success' => false, 'message' => 'Quiz not found.'], 404);
        }

        $userId = $request->user()->id;
        $enrollment = $this->enrollmentService->getEnrollment($userId, $courseId);
        if (!$enrollment) {
            return response()->json(['success' => false, 'message' => 'You must be enrolled to submit quiz attempts.'], 403);
        }

        try {
            $attempt = $this->quizService->submitAttempt($userId, $quizId, $request->validated()['answers']);

            // Triggers progress recheck (since passing this quiz might unlock course completion)
            $courseCompleted = $this->progressService->checkAndCompleteCourse($userId, $courseId);

            return response()->json([
                'success' => true,
                'message' => $attempt->passed ? 'Quiz passed!' : 'Quiz failed. Try again!',
                'attempt' => new QuizAttemptResource($attempt),
                'course_completed' => $courseCompleted,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get authenticated user's certificates.
     */
    public function certificates(Request $request): JsonResponse
    {
        $certificates = $this->certificateService->getUserCertificates($request->user()->id);

        return response()->json([
            'success' => true,
            'certificates' => CertificateResource::collection($certificates->load(['certificate.course', 'certificate.learningPath', 'user'])),
        ]);
    }

    /**
     * Verify a certificate by its unique code or token.
     */
    public function verifyCertificate(Request $request, string $code): JsonResponse
    {
        try {
            $certificate = $this->certificateService->verifyCertificate($code, $request->ip(), $request->userAgent());
            return response()->json([
                'success' => true,
                'verified' => true,
                'certificate' => new CertificateResource($certificate->load(['certificate.course', 'certificate.learningPath', 'user'])),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid certificate code. This certificate cannot be verified.',
            ], 404);
        }
    }

    /**
     * Get all learning paths.
     */
    public function learningPaths(): JsonResponse
    {
        $paths = $this->learningPathService->getAllLearningPaths();

        return response()->json([
            'success' => true,
            'learning_paths' => LearningPathResource::collection($paths->load('courses')),
        ]);
    }

    /**
     * Get achievements for authenticated student.
     */
    public function achievements(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $achievements = app(AchievementService::class)->getAchievementsForUser($userId);

        return response()->json([
            'success' => true,
            'achievements' => AchievementResource::collection($achievements),
            'points' => $achievements->filter(fn($a) => $a->awards->isNotEmpty())->sum('points'),
        ]);
    }

    /**
     * Get badges for authenticated student.
     */
    public function badges(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $badges = app(BadgeService::class)->getBadgesForUser($userId);

        return response()->json([
            'success' => true,
            'badges' => BadgeResource::collection($badges),
        ]);
    }

    /**
     * Get milestones for authenticated student.
     */
    public function milestones(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $milestones = app(MilestoneService::class)->getMilestonesForUser($userId);

        return response()->json([
            'success' => true,
            'milestones' => MilestoneResource::collection($milestones),
        ]);
    }

    /**
     * List quiz attempts for the authenticated user.
     */
    public function quizAttempts(Request $request, QuizRepository $quizRepository): JsonResponse
    {
        $quizId = $request->query('quiz_id');
        $attempts = $quizRepository->getUserAttempts(
            $request->user()->id,
            $quizId !== null ? (int) $quizId : null
        );

        return response()->json([
            'success' => true,
            'attempts' => QuizAttemptResource::collection($attempts),
        ]);
    }

    /**
     * Student-facing scholastic resources vault.
     */
    public function resources(): JsonResponse
    {
        $resources = CmsResource::where('status', 'published')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($item) => [
                'id' => $item->uuid,
                'title' => $item->title,
                'category' => $item->category ?? 'Handbook',
                'size' => $item->file ? 'Download' : ($item->is_external ? 'External Link' : 'Digital Asset'),
                'description' => $item->description,
                'lessons' => $item->tags ?? [],
                'url' => $item->link,
                'linkText' => $item->is_external ? 'Open Resource' : 'Download',
            ]);

        return response()->json([
            'success' => true,
            'resources' => $resources,
        ]);
    }
}
