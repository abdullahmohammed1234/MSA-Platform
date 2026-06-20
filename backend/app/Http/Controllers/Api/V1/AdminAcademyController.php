<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academy\CourseRequest;
use App\Http\Requests\Academy\ModuleRequest;
use App\Http\Requests\Academy\LessonRequest;
use App\Http\Requests\Academy\QuizRequest;
use App\Http\Requests\Academy\QuestionRequest;
use App\Http\Requests\Academy\LearningPathRequest;
use App\Http\Resources\Academy\CourseResource;
use App\Http\Resources\Academy\ModuleResource;
use App\Http\Resources\Academy\LessonResource;
use App\Http\Resources\Academy\QuizResource;
use App\Http\Resources\Academy\QuestionResource;
use App\Http\Resources\Academy\CertificateResource;
use App\Http\Resources\Academy\LearningPathResource;
use App\Http\Resources\Academy\CertificateTemplateResource;
use App\Http\Resources\Academy\AchievementResource;
use App\Http\Resources\Academy\BadgeResource;
use App\Http\Resources\Academy\MilestoneResource;
use App\Models\Course;
use App\Models\Module;
use App\Models\Lesson;
use App\Models\Quiz;
use App\Models\Question;
use App\Models\LearningPath;
use App\Models\Enrollment;
use App\Models\QuizAttempt;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\CertificateAward;
use App\Models\Achievement;
use App\Models\Badge;
use App\Models\Milestone;
use App\Models\DiscussionReport;
use App\Models\MentorAssignment;
use App\Models\User;
use App\Services\CourseService;
use App\Services\ModuleService;
use App\Services\LessonService;
use App\Services\QuizService;
use App\Services\LearningPathService;
use App\Services\CertificateService;
use App\Services\QuestionBankService;
use App\Services\StudentManagementService;
use App\Services\MentorAssignmentService;
use App\Services\ProgressManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminAcademyController extends Controller
{
    protected $courseService;
    protected $moduleService;
    protected $lessonService;
    protected $quizService;
    protected $learningPathService;

    public function __construct(
        CourseService $courseService,
        ModuleService $moduleService,
        LessonService $lessonService,
        QuizService $quizService,
        LearningPathService $learningPathService
    ) {
        $this->courseService = $courseService;
        $this->moduleService = $moduleService;
        $this->lessonService = $lessonService;
        $this->quizService = $quizService;
        $this->learningPathService = $learningPathService;
    }

    // --- Course CRUD ---

    public function indexCourses(Request $request): JsonResponse
    {
        $this->authorize('create', Course::class);

        $filters = $request->only(['status', 'difficulty', 'search']);
        $courses = $this->courseService->getAdminCourses($filters, $request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'courses' => CourseResource::collection($courses),
            'meta' => [
                'current_page' => $courses->currentPage(),
                'last_page' => $courses->lastPage(),
                'total' => $courses->total(),
            ]
        ]);
    }

    public function storeCourse(CourseRequest $request): JsonResponse
    {
        $this->authorize('create', Course::class);

        $data = $request->validated();
        $data['created_by'] = $request->user()->id;

        $course = $this->courseService->createCourse($data);
        $this->invalidateCoursesCache();

        return response()->json([
            'success' => true,
            'message' => 'Course created successfully.',
            'course' => new CourseResource($course->load('creator')),
        ], 201);
    }

    public function updateCourse(CourseRequest $request, Course $course): JsonResponse
    {
        $this->authorize('update', $course);

        $this->courseService->updateCourse($course, $request->validated());
        $this->invalidateCoursesCache();

        return response()->json([
            'success' => true,
            'message' => 'Course updated successfully.',
            'course' => new CourseResource($course->fresh('creator')),
        ]);
    }

    public function destroyCourse(Course $course): JsonResponse
    {
        $this->authorize('delete', $course);

        $this->courseService->deleteCourse($course);
        $this->invalidateCoursesCache();

        return response()->json([
            'success' => true,
            'message' => 'Course deleted successfully.',
        ]);
    }

    // --- Module CRUD ---

    public function storeModule(ModuleRequest $request): JsonResponse
    {
        $this->authorize('create', Module::class);

        $module = $this->moduleService->createModule($request->validated());
        $this->invalidateCoursesCache();

        return response()->json([
            'success' => true,
            'message' => 'Module created successfully.',
            'module' => new ModuleResource($module),
        ], 201);
    }

    public function updateModule(ModuleRequest $request, Module $module): JsonResponse
    {
        $this->authorize('update', $module);

        $this->moduleService->updateModule($module, $request->validated());
        $this->invalidateCoursesCache();

        return response()->json([
            'success' => true,
            'message' => 'Module updated successfully.',
            'module' => new ModuleResource($module->fresh()),
        ]);
    }

    public function destroyModule(Module $module): JsonResponse
    {
        $this->authorize('delete', $module);

        $this->moduleService->deleteModule($module);
        $this->invalidateCoursesCache();

        return response()->json([
            'success' => true,
            'message' => 'Module deleted successfully.',
        ]);
    }

    public function reorderModules(Request $request, int $courseId): JsonResponse
    {
        $request->validate([
            'modules' => 'required|array',
            'modules.*' => 'integer|exists:modules,id',
        ]);

        $this->authorize('create', Module::class);

        $this->moduleService->reorderModules($courseId, $request->input('modules'));
        $this->invalidateCoursesCache();

        return response()->json([
            'success' => true,
            'message' => 'Modules reordered successfully.',
        ]);
    }

    // --- Lesson CRUD ---

    public function storeLesson(LessonRequest $request): JsonResponse
    {
        $this->authorize('create', Lesson::class);

        $lesson = $this->lessonService->createLesson($request->validated());
        $this->invalidateCoursesCache();

        return response()->json([
            'success' => true,
            'message' => 'Lesson created successfully.',
            'lesson' => new LessonResource($lesson),
        ], 201);
    }

    public function updateLesson(LessonRequest $request, Lesson $lesson): JsonResponse
    {
        $this->authorize('update', $lesson);

        $this->lessonService->updateLesson($lesson, $request->validated());
        $this->invalidateCoursesCache();

        return response()->json([
            'success' => true,
            'message' => 'Lesson updated successfully.',
            'lesson' => new LessonResource($lesson->fresh()),
        ]);
    }

    public function destroyLesson(Lesson $lesson): JsonResponse
    {
        $this->authorize('delete', $lesson);

        $this->lessonService->deleteLesson($lesson);
        $this->invalidateCoursesCache();

        return response()->json([
            'success' => true,
            'message' => 'Lesson deleted successfully.',
        ]);
    }

    public function reorderLessons(Request $request, int $moduleId): JsonResponse
    {
        $request->validate([
            'lessons' => 'required|array',
            'lessons.*' => 'integer|exists:lessons,id',
        ]);

        $this->authorize('create', Lesson::class);

        $this->lessonService->reorderLessons($moduleId, $request->input('lessons'));
        $this->invalidateCoursesCache();

        return response()->json([
            'success' => true,
            'message' => 'Lessons reordered successfully.',
        ]);
    }

    // --- Quiz CRUD ---

    public function indexQuizzes(Request $request): JsonResponse
    {
        $this->authorize('create', Quiz::class);

        $filters = $request->only(['course_id', 'status', 'search']);
        $quizzes = $this->quizService->getAdminQuizzes($filters, $request->get('per_page', 50));

        return response()->json([
            'success' => true,
            'quizzes' => QuizResource::collection($quizzes),
            'meta' => [
                'current_page' => $quizzes->currentPage(),
                'last_page' => $quizzes->lastPage(),
                'total' => $quizzes->total(),
            ],
        ]);
    }

    public function storeQuiz(QuizRequest $request): JsonResponse
    {
        $this->authorize('create', Quiz::class);

        $quiz = $this->quizService->createQuiz($request->validated());
        $this->invalidateCoursesCache();

        return response()->json([
            'success' => true,
            'message' => 'Quiz created successfully.',
            'quiz' => new QuizResource($quiz),
        ], 201);
    }

    public function updateQuiz(QuizRequest $request, Quiz $quiz): JsonResponse
    {
        $this->authorize('update', $quiz);

        $this->quizService->updateQuiz($quiz, $request->validated());
        $this->invalidateCoursesCache();

        return response()->json([
            'success' => true,
            'message' => 'Quiz updated successfully.',
            'quiz' => new QuizResource($quiz->fresh()),
        ]);
    }

    public function destroyQuiz(Quiz $quiz): JsonResponse
    {
        $this->authorize('delete', $quiz);

        $this->quizService->deleteQuiz($quiz);
        $this->invalidateCoursesCache();

        return response()->json([
            'success' => true,
            'message' => 'Quiz deleted successfully.',
        ]);
    }

    // --- Question CRUD ---

    public function storeQuestion(QuestionRequest $request): JsonResponse
    {
        $this->authorize('create', Question::class);

        $question = $this->quizService->createQuestion($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Question created successfully.',
            'question' => new QuestionResource($question),
        ], 201);
    }

    public function updateQuestion(QuestionRequest $request, Question $question): JsonResponse
    {
        $this->authorize('update', $question);

        $this->quizService->updateQuestion($question, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Question updated successfully.',
            'question' => new QuestionResource($question->fresh()),
        ]);
    }

    public function destroyQuestion(Question $question): JsonResponse
    {
        $this->authorize('delete', $question);

        $this->quizService->deleteQuestion($question);

        return response()->json([
            'success' => true,
            'message' => 'Question deleted successfully.',
        ]);
    }

    // --- LearningPath CRUD ---

    public function indexLearningPaths(Request $request): JsonResponse
    {
        $this->authorize('create', LearningPath::class);

        $paths = $this->learningPathService->getAllLearningPaths();

        return response()->json([
            'success' => true,
            'learning_paths' => LearningPathResource::collection($paths->load('courses')),
        ]);
    }

    public function storeLearningPath(LearningPathRequest $request): JsonResponse
    {
        $this->authorize('create', LearningPath::class);

        $path = $this->learningPathService->createLearningPath($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Learning path created successfully.',
            'learning_path' => new LearningPathResource($path),
        ], 201);
    }

    public function updateLearningPath(LearningPathRequest $request, LearningPath $learningPath): JsonResponse
    {
        $this->authorize('update', $learningPath);

        $this->learningPathService->updateLearningPath($learningPath, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Learning path updated successfully.',
            'learning_path' => new LearningPathResource($learningPath->fresh()),
        ]);
    }

    public function destroyLearningPath(LearningPath $learningPath): JsonResponse
    {
        $this->authorize('delete', $learningPath);

        $this->learningPathService->deleteLearningPath($learningPath);

        return response()->json([
            'success' => true,
            'message' => 'Learning path deleted successfully.',
        ]);
    }

    public function assignCourseToPath(Request $request, LearningPath $learningPath): JsonResponse
    {
        $request->validate([
            'course_id' => 'required|integer|exists:courses,id',
            'order' => 'nullable|integer|min:0',
        ]);

        $this->authorize('update', $learningPath);

        $order = $request->input('order', $learningPath->courses()->count() + 1);

        $this->learningPathService->assignCourseToPath($learningPath, $request->input('course_id'), $order);

        return response()->json([
            'success' => true,
            'message' => 'Course assigned to learning path successfully.',
        ]);
    }

    public function removeCourseFromPath(LearningPath $learningPath, Course $course): JsonResponse
    {
        $this->authorize('update', $learningPath);

        $this->learningPathService->removeCourseFromPath($learningPath, $course->id);

        return response()->json([
            'success' => true,
            'message' => 'Course removed from learning path successfully.',
        ]);
    }

    public function reorderCoursesInPath(Request $request, LearningPath $learningPath): JsonResponse
    {
        $request->validate([
            'courses' => 'required|array',
            'courses.*' => 'integer|exists:courses,id',
        ]);

        $this->authorize('update', $learningPath);

        $this->learningPathService->reorderCoursesInPath($learningPath, $request->input('courses'));

        return response()->json([
            'success' => true,
            'message' => 'Learning path courses reordered successfully.',
        ]);
    }

    // --- LMS Analytics Indicator Preparation ---

    public function analytics(): JsonResponse
    {
        // Must have view_analytics/view_reports
        if (!auth()->user()->hasPermission('view_analytics') && !auth()->user()->hasPermission('view_reports')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $totalCourses = Course::count();
        $totalUsersEnrolled = Enrollment::distinct('user_id')->count('user_id');

        $enrollmentStats = Enrollment::select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->get()
            ->pluck('count', 'status')
            ->toArray();

        $courseCompletionRates = Enrollment::select('course_id', DB::raw('count(*) as total'), DB::raw('sum(case when status = "completed" then 1 else 0 end) as completed'))
            ->groupBy('course_id')
            ->with(['course' => function ($q) { $q->select('id', 'title'); }])
            ->get()
            ->map(function ($stat) {
                return [
                    'course_id' => $stat->course_id,
                    'title' => $stat->course ? $stat->course->title : 'Unknown',
                    'total' => $stat->total,
                    'completed' => (int) $stat->completed,
                    'completion_rate' => $stat->total > 0 ? (float) number_format(($stat->completed / $stat->total) * 100, 2) : 0
                ];
            });

        $quizStats = QuizAttempt::select('quiz_id', DB::raw('count(*) as attempts_count'), DB::raw('avg(score) as average_score'), DB::raw('sum(case when passed = 1 then 1 else 0 end) as passed_count'))
            ->groupBy('quiz_id')
            ->with(['quiz' => function ($q) { $q->select('id', 'title'); }])
            ->get()
            ->map(function ($stat) {
                return [
                    'quiz_id' => $stat->quiz_id,
                    'title' => $stat->quiz ? $stat->quiz->title : 'Unknown',
                    'attempts' => $stat->attempts_count,
                    'average_score' => (float) number_format($stat->average_score, 2),
                    'passed' => (int) $stat->passed_count,
                    'passing_rate' => $stat->attempts_count > 0 ? (float) number_format(($stat->passed_count / $stat->attempts_count) * 100, 2) : 0
                ];
            });

        $recentEnrollments = Enrollment::with(['user:id,name', 'course:id,title'])
            ->latest('enrolled_at')
            ->limit(5)
            ->get()
            ->map(fn ($enrollment) => [
                'id' => 'enrollment-' . $enrollment->id,
                'badge' => '🎓',
                'text' => ($enrollment->user?->name ?? 'A student') . ' enrolled in "' . ($enrollment->course?->title ?? 'a course') . '"',
                'timestamp' => $enrollment->enrolled_at?->toIso8601String(),
                'time' => $enrollment->enrolled_at?->diffForHumans() ?? 'Recently',
            ]);

        $recentCertificates = CertificateAward::with('user:id,name')
            ->latest('issued_at')
            ->limit(5)
            ->get()
            ->map(fn ($award) => [
                'id' => 'cert-' . $award->id,
                'badge' => '🏆',
                'text' => ($award->user?->name ?? 'A student') . ' completed "' . ($award->title ?? 'Certificate') . '"',
                'timestamp' => $award->issued_at?->toIso8601String(),
                'time' => $award->issued_at?->diffForHumans() ?? 'Recently',
            ]);

        $recentQuizAttempts = QuizAttempt::with(['user:id,name', 'quiz:id,title'])
            ->whereNotNull('submitted_at')
            ->latest('submitted_at')
            ->limit(5)
            ->get()
            ->map(fn ($attempt) => [
                'id' => 'quiz-' . $attempt->id,
                'badge' => '📝',
                'text' => ($attempt->user?->name ?? 'A student') . ' scored ' . $attempt->score . '% on "' . ($attempt->quiz?->title ?? 'Quiz') . '" (' . ($attempt->passed ? 'passed' : 'failed') . ')',
                'timestamp' => $attempt->submitted_at?->toIso8601String(),
                'time' => $attempt->submitted_at?->diffForHumans() ?? 'Recently',
            ]);

        $recentActivity = $recentEnrollments
            ->concat($recentCertificates)
            ->concat($recentQuizAttempts)
            ->sortByDesc('timestamp')
            ->take(8)
            ->values()
            ->map(fn ($item) => [
                'id' => $item['id'],
                'badge' => $item['badge'],
                'text' => $item['text'],
                'time' => $item['time'],
            ]);

        $activeMentors = User::whereHas('roles', fn ($q) => $q->where('slug', 'mentor'))->count();
        $activeAssignments = MentorAssignment::where('status', 'active')->count();
        $avgStudentsPerMentor = $activeMentors > 0
            ? round($activeAssignments / $activeMentors, 1)
            : 0;

        $totalQuizAttempts = QuizAttempt::count();
        $passedQuizAttempts = QuizAttempt::where('passed', true)->count();
        $quizPassRate = $totalQuizAttempts > 0
            ? (int) round(($passedQuizAttempts / $totalQuizAttempts) * 100)
            : 0;

        $openReports = DiscussionReport::where('status', 'open')->count();
        $certificatesIssued = CertificateAward::count();

        return response()->json([
            'success' => true,
            'summary' => [
                'courses_count' => $totalCourses,
                'enrolled_users_count' => $totalUsersEnrolled,
                'certificates_issued' => $certificatesIssued,
                'enrollments' => [
                    'active' => $enrollmentStats['active'] ?? 0,
                    'completed' => $enrollmentStats['completed'] ?? 0,
                    'dropped' => $enrollmentStats['dropped'] ?? 0,
                ],
                'course_performance' => $courseCompletionRates,
                'quiz_performance' => $quizStats
            ],
            'recent_activity' => $recentActivity,
            'alerts' => [
                'open_reports' => $openReports,
                'certificates_issued' => $certificatesIssued,
            ],
            'workload' => [
                'avg_students_per_mentor' => $avgStudentsPerMentor,
                'mentor_capacity' => 15,
                'quiz_pass_rate' => $quizPassRate,
            ],
        ]);
    }

    // --- Certificate Template CRUD ---

    public function indexTemplates(): JsonResponse
    {
        $this->authorize('create', CertificateTemplate::class);
        $templates = CertificateTemplate::all();
        return response()->json([
            'success' => true,
            'templates' => CertificateTemplateResource::collection($templates),
        ]);
    }

    public function storeTemplate(Request $request): JsonResponse
    {
        $this->authorize('create', CertificateTemplate::class);
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'title_template' => 'required|string|max:255',
            'description_template' => 'nullable|string',
            'layout' => 'required|string|in:landscape,portrait',
            'branding' => 'nullable|array',
            'signatures' => 'nullable|array',
            'background_asset' => 'nullable|string',
        ]);

        $template = CertificateTemplate::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Template created successfully.',
            'template' => new CertificateTemplateResource($template),
        ], 201);
    }

    public function updateTemplate(Request $request, CertificateTemplate $template): JsonResponse
    {
        $this->authorize('update', $template);
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'title_template' => 'sometimes|required|string|max:255',
            'description_template' => 'nullable|string',
            'layout' => 'sometimes|required|string|in:landscape,portrait',
            'branding' => 'nullable|array',
            'signatures' => 'nullable|array',
            'background_asset' => 'nullable|string',
            'status' => 'sometimes|required|string|in:active,inactive',
        ]);

        $template->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Template updated successfully.',
            'template' => new CertificateTemplateResource($template),
        ]);
    }

    public function destroyTemplate(CertificateTemplate $template): JsonResponse
    {
        $this->authorize('delete', $template);
        $template->delete();

        return response()->json([
            'success' => true,
            'message' => 'Template deleted successfully.',
        ]);
    }

    // --- Achievement CRUD ---

    public function indexAchievements(): JsonResponse
    {
        $this->authorize('create', Achievement::class);
        $achievements = Achievement::all();
        return response()->json([
            'success' => true,
            'achievements' => AchievementResource::collection($achievements),
        ]);
    }

    public function storeAchievement(Request $request): JsonResponse
    {
        $this->authorize('create', Achievement::class);
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'required|string|unique:achievements,slug',
            'description' => 'nullable|string',
            'type' => 'required|string|in:completion,performance,participation,special_recognition',
            'points' => 'required|integer|min:0',
            'criteria_type' => 'required|string',
            'criteria_value' => 'nullable|array',
        ]);

        $achievement = Achievement::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Achievement created successfully.',
            'achievement' => new AchievementResource($achievement),
        ], 201);
    }

    public function updateAchievement(Request $request, Achievement $achievement): JsonResponse
    {
        $this->authorize('update', $achievement);
        $data = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'slug' => 'sometimes|required|string|unique:achievements,slug,' . $achievement->id,
            'description' => 'nullable|string',
            'type' => 'sometimes|required|string|in:completion,performance,participation,special_recognition',
            'points' => 'sometimes|required|integer|min:0',
            'criteria_type' => 'sometimes|required|string',
            'criteria_value' => 'nullable|array',
        ]);

        $achievement->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Achievement updated successfully.',
            'achievement' => new AchievementResource($achievement),
        ]);
    }

    public function destroyAchievement(Achievement $achievement): JsonResponse
    {
        $this->authorize('delete', $achievement);
        $achievement->delete();

        return response()->json([
            'success' => true,
            'message' => 'Achievement deleted successfully.',
        ]);
    }

    // --- Badge CRUD ---

    public function indexBadges(): JsonResponse
    {
        $this->authorize('create', Badge::class);
        $badges = Badge::all();
        return response()->json([
            'success' => true,
            'badges' => BadgeResource::collection($badges),
        ]);
    }

    public function storeBadge(Request $request): JsonResponse
    {
        $this->authorize('create', Badge::class);
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|unique:badges,slug',
            'description' => 'nullable|string',
            'image_path' => 'nullable|string',
            'criteria_type' => 'required|string',
            'criteria_value' => 'nullable|array',
        ]);

        $badge = Badge::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Badge created successfully.',
            'badge' => new BadgeResource($badge),
        ], 201);
    }

    public function updateBadge(Request $request, Badge $badge): JsonResponse
    {
        $this->authorize('update', $badge);
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'slug' => 'sometimes|required|string|unique:badges,slug,' . $badge->id,
            'description' => 'nullable|string',
            'image_path' => 'nullable|string',
            'criteria_type' => 'sometimes|required|string',
            'criteria_value' => 'nullable|array',
        ]);

        $badge->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Badge updated successfully.',
            'badge' => new BadgeResource($badge),
        ]);
    }

    public function destroyBadge(Badge $badge): JsonResponse
    {
        $this->authorize('delete', $badge);
        $badge->delete();

        return response()->json([
            'success' => true,
            'message' => 'Badge deleted successfully.',
        ]);
    }

    // --- User Certificate Awards Administration ---

    public function indexDefinitions(): JsonResponse
    {
        if (!auth()->user()->hasPermission('manage_certificates')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $certificates = Certificate::with(['course', 'learningPath'])->get();

        return response()->json([
            'success' => true,
            'certificates' => $certificates,
        ]);
    }

    public function indexAwards(): JsonResponse
    {
        // Protected by manage_certificates permission via Controller validation
        if (!auth()->user()->hasPermission('manage_certificates')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $awards = CertificateAward::with(['user', 'certificate.course', 'certificate.learningPath', 'issuer'])->latest()->get();

        return response()->json([
            'success' => true,
            'awards' => CertificateResource::collection($awards),
        ]);
    }

    public function storeAward(Request $request): JsonResponse
    {
        if (!auth()->user()->hasPermission('manage_certificates')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $data = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'certificate_id' => 'required|integer|exists:certificates,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $award = app(CertificateService::class)->issueSpecialCertificate(
            $data['user_id'],
            $data['certificate_id'],
            $data['title'],
            $data['description'],
            auth()->id()
        );

        return response()->json([
            'success' => true,
            'message' => 'Certificate manually awarded successfully.',
            'award' => new CertificateResource($award->load(['user', 'certificate'])),
        ], 201);
    }

    public function revokeAward(int $id): JsonResponse
    {
        if (!auth()->user()->hasPermission('manage_certificates')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $deleted = app(CertificateService::class)->revokeCertificateAward($id);

        if (!$deleted) {
            return response()->json([
                'success' => false,
                'message' => 'Certificate award not found or already revoked.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Certificate award revoked successfully.',
        ]);
    }

    // --- Centralized Question Bank Index ---

    public function indexQuestions(Request $request, QuestionBankService $service): JsonResponse
    {
        if (!auth()->user()->hasPermission('manage_quizzes') && !auth()->user()->hasPermission('manage_question_bank')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $filters = $request->only(['search', 'type', 'category', 'difficulty', 'quiz_id']);
        $questions = $service->getQuestions($filters, $request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'questions' => QuestionResource::collection($questions),
            'meta' => [
                'current_page' => $questions->currentPage(),
                'last_page' => $questions->lastPage(),
                'total' => $questions->total(),
            ]
        ]);
    }

    // --- Student Management ---

    public function indexStudents(Request $request, StudentManagementService $service): JsonResponse
    {
        if (!auth()->user()->hasPermission('manage_students') && !auth()->user()->hasPermission('manage_volunteers')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $filters = $request->only(['search', 'status']);
        $students = $service->getStudents($filters, $request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'students' => $students->items(),
            'meta' => [
                'current_page' => $students->currentPage(),
                'last_page' => $students->lastPage(),
                'total' => $students->total(),
            ]
        ]);
    }

    public function showStudent(User $student, StudentManagementService $service): JsonResponse
    {
        if (!auth()->user()->hasPermission('manage_students') && !auth()->user()->hasPermission('manage_volunteers')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $profile = $service->getStudentProfile($student);

        return response()->json([
            'success' => true,
            'student' => $profile,
        ]);
    }

    public function suspendStudent(User $student, StudentManagementService $service): JsonResponse
    {
        if (!auth()->user()->hasPermission('manage_students') && !auth()->user()->hasPermission('manage_volunteers')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $service->suspendAccess($student);

        return response()->json([
            'success' => true,
            'message' => 'Student access suspended successfully.',
            'student' => [
                'id' => $student->id,
                'is_active' => false
            ]
        ]);
    }

    public function reactivateStudent(User $student, StudentManagementService $service): JsonResponse
    {
        if (!auth()->user()->hasPermission('manage_students') && !auth()->user()->hasPermission('manage_volunteers')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $service->reactivateAccess($student);

        return response()->json([
            'success' => true,
            'message' => 'Student access reactivated successfully.',
            'student' => [
                'id' => $student->id,
                'is_active' => true
            ]
        ]);
    }

    // --- Mentor Management & Assignments ---

    public function indexMentors(Request $request, MentorAssignmentService $service): JsonResponse
    {
        if (!auth()->user()->hasPermission('manage_mentors')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $filters = $request->only(['search']);
        $mentors = $service->getMentors($filters, $request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'mentors' => $mentors->items(),
            'meta' => [
                'current_page' => $mentors->currentPage(),
                'last_page' => $mentors->lastPage(),
                'total' => $mentors->total(),
            ]
        ]);
    }

    public function showMentor(User $mentor, MentorAssignmentService $service): JsonResponse
    {
        if (!auth()->user()->hasPermission('manage_mentors')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $profile = $service->getMentorProfile($mentor);

        return response()->json([
            'success' => true,
            'mentor' => $profile,
        ]);
    }

    public function indexAssignments(Request $request, MentorAssignmentService $service): JsonResponse
    {
        if (!auth()->user()->hasPermission('manage_mentors') && !auth()->user()->hasPermission('assign_mentors')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $assignments = \App\Models\MentorAssignment::with(['mentor', 'student', 'assignedBy'])->latest()->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'assignments' => $assignments->items(),
            'meta' => [
                'current_page' => $assignments->currentPage(),
                'last_page' => $assignments->lastPage(),
                'total' => $assignments->total(),
            ]
        ]);
    }

    public function storeAssignment(Request $request, MentorAssignmentService $service): JsonResponse
    {
        if (!auth()->user()->hasPermission('manage_mentors') && !auth()->user()->hasPermission('assign_mentors')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $data = $request->validate([
            'mentor_id' => 'required|integer|exists:users,id',
            'student_id' => 'required|integer|exists:users,id',
            'notes' => 'nullable|string',
        ]);

        try {
            $assignment = $service->assignMentor($data['mentor_id'], $data['student_id'], auth()->id(), $data['notes']);
            return response()->json([
                'success' => true,
                'message' => 'Mentor assigned successfully.',
                'assignment' => $assignment,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function destroyAssignment(int $mentorId, int $studentId, MentorAssignmentService $service): JsonResponse
    {
        if (!auth()->user()->hasPermission('manage_mentors') && !auth()->user()->hasPermission('assign_mentors')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $service->removeAssignment($mentorId, $studentId);

        return response()->json([
            'success' => true,
            'message' => 'Mentor assignment removed successfully.',
        ]);
    }

    public function bulkAssign(Request $request, MentorAssignmentService $service): JsonResponse
    {
        if (!auth()->user()->hasPermission('manage_mentors') && !auth()->user()->hasPermission('assign_mentors')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $data = $request->validate([
            'mentor_id' => 'required|integer|exists:users,id',
            'student_ids' => 'required|array',
            'student_ids.*' => 'integer|exists:users,id',
        ]);

        $result = $service->bulkAssign($data['mentor_id'], $data['student_ids'], auth()->id());

        return response()->json([
            'success' => true,
            'message' => 'Bulk assignment completed.',
            'results' => $result,
        ]);
    }

    // --- Progress Tracking ---

    public function indexProgress(Request $request, ProgressManagementService $service): JsonResponse
    {
        if (!auth()->user()->hasPermission('view_progress') && !auth()->user()->hasPermission('view_student_progress')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $filters = $request->only(['student_id', 'course_id', 'mentor_id', 'status', 'start_date', 'end_date']);
        $progress = $service->getStudentProgress($filters, $request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'progress' => $progress->items(),
            'meta' => [
                'current_page' => $progress->currentPage(),
                'last_page' => $progress->lastPage(),
                'total' => $progress->total(),
            ]
        ]);
    }

    // --- Reissue Certificate ---

    public function reissueAward(int $id): JsonResponse
    {
        if (!auth()->user()->hasPermission('manage_certificates')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $award = CertificateAward::find($id);
        if (!$award) {
            return response()->json(['success' => false, 'message' => 'Certificate award not found.'], 404);
        }

        $award->update(['issued_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'Certificate successfully reissued.',
            'award' => $award,
        ]);
    }

    protected function invalidateCoursesCache(): void
    {
        try {
            if (!\Illuminate\Support\Facades\Cache::has('academy_courses_version')) {
                \Illuminate\Support\Facades\Cache::put('academy_courses_version', 1, 86400 * 30);
            } else {
                \Illuminate\Support\Facades\Cache::increment('academy_courses_version');
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Cache::put('academy_courses_version', 1, 86400 * 30);
        }
    }
}
