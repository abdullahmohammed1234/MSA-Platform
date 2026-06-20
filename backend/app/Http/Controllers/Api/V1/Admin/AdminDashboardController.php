<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\CertificateAward;
use App\Models\Course;
use App\Models\DiscussionReport;
use App\Models\Enrollment;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminDashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        if (
            !$user->hasPermission('view_analytics')
            && !$user->hasRole('super-admin')
            && !$user->hasRole('admin')
        ) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

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
                'text' => ($award->user?->name ?? 'A student') . ' earned "' . ($award->title ?? 'Certificate') . '"',
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

        return response()->json([
            'success' => true,
            'stats' => [
                'total_users' => User::count(),
                'active_students' => Enrollment::where('status', 'active')->distinct('user_id')->count('user_id'),
                'published_courses' => Course::where('status', 'published')->count(),
                'pending_inquiries' => DiscussionReport::where('status', 'open')->count(),
            ],
            'recent_activity' => $recentActivity,
        ]);
    }
}
