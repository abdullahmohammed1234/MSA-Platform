<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DiscussionReport;
use App\Models\DiscussionThread;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminDiscussionController extends Controller
{
    public function reports(Request $request): JsonResponse
    {
        $status = $request->query('status', 'open');

        $reports = DiscussionReport::with(['user', 'thread.author', 'thread.category', 'post'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->latest()
            ->get()
            ->map(function (DiscussionReport $report) {
                $thread = $report->thread;

                return [
                    'id' => (string) $report->id,
                    'reason' => $report->reason,
                    'status' => $report->status,
                    'reportedAt' => $report->created_at->toIso8601String(),
                    'reporterName' => $report->user?->name ?? 'Anonymous',
                    'thread' => $thread ? [
                        'id' => (string) $thread->id,
                        'title' => $thread->title,
                        'preview' => \Illuminate\Support\Str::limit($thread->content, 180),
                        'authorName' => $thread->author?->name ?? 'User',
                        'courseTitle' => $thread->category?->name ?? 'General',
                        'postedAt' => $thread->created_at->toIso8601String(),
                        'repliesCount' => $thread->posts()->count(),
                        'status' => $report->status === 'open' ? 'flagged' : 'resolved',
                        'flaggedReason' => $report->reason,
                        'flaggedCount' => 1,
                    ] : null,
                ];
            })
            ->filter(fn ($item) => $item['thread'] !== null)
            ->values();

        return response()->json([
            'success' => true,
            'reports' => $reports,
        ]);
    }

    public function resolve(Request $request, DiscussionReport $report): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:resolved,dismissed',
        ]);

        $report->update(['status' => $validated['status']]);

        return response()->json([
            'success' => true,
            'report' => [
                'id' => (string) $report->id,
                'status' => $report->status,
            ],
        ]);
    }

    public function threads(Request $request): JsonResponse
    {
        $threads = DiscussionThread::with(['author', 'category'])
            ->latest()
            ->limit(50)
            ->get()
            ->map(function (DiscussionThread $thread) {
                $openReports = DiscussionReport::where('thread_id', $thread->id)->where('status', 'open')->count();

                return [
                    'id' => (string) $thread->id,
                    'authorName' => $thread->author?->name ?? 'User',
                    'authorAvatar' => $thread->author?->avatar ?? '',
                    'title' => $thread->title,
                    'preview' => \Illuminate\Support\Str::limit($thread->content, 180),
                    'courseTitle' => $thread->category?->name ?? 'General',
                    'flaggedCount' => $openReports,
                    'flaggedReason' => $openReports > 0 ? 'Open moderation reports' : null,
                    'repliesCount' => $thread->posts()->count(),
                    'postedAt' => $thread->created_at->toIso8601String(),
                    'status' => $openReports > 0 ? 'flagged' : 'active',
                    'isPinned' => false,
                    'mentorReplies' => [],
                ];
            });

        return response()->json([
            'success' => true,
            'discussions' => $threads,
        ]);
    }
}
