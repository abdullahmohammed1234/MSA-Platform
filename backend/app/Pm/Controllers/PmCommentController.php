<?php

namespace App\Pm\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Pm\Models\PmActivityLog;
use App\Pm\Models\PmComment;
use App\Pm\Models\PmProject;
use App\Pm\Models\PmTask;
use App\Pm\Services\PmNotificationService;
use App\Pm\Services\ProjectAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class PmCommentController extends Controller
{
    public function __construct(
        protected ProjectAccessService $accessService,
        protected PmNotificationService $notificationService
    ) {}

    /**
     * List comments for a project or specific task.
     */
    public function index(Request $request, string $projectUuid): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $project = PmProject::where('uuid', $projectUuid)->firstOrFail();

        if (!$this->accessService->canViewProject($user, $project)) {
            abort(403, 'Unauthorized.');
        }

        $query = PmComment::query()
            ->where('project_id', $project->id)
            ->with(['author:id,name,email,avatar'])
            ->orderBy('created_at', 'asc');

        if ($request->has('task_uuid') && !empty($request->query('task_uuid'))) {
            $task = PmTask::where('project_id', $project->id)
                ->where('uuid', $request->query('task_uuid'))
                ->firstOrFail();
            $query->where('task_id', $task->id);
        } elseif ($request->has('task_id') && !empty($request->query('task_id'))) {
            $query->where('task_id', $request->query('task_id'));
        }

        $comments = $query->get();

        return response()->json([
            'success' => true,
            'data' => $comments,
        ]);
    }

    /**
     * Store a comment on a project or task.
     */
    public function store(Request $request, string $projectUuid): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $project = PmProject::where('uuid', $projectUuid)->firstOrFail();

        if (!$this->accessService->canAddComment($user, $project)) {
            abort(403, 'Unauthorized: Viewers cannot post comments.');
        }

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:10000'],
            'task_id' => ['nullable', 'integer', 'exists:pm_tasks,id'],
            'task_uuid' => ['nullable', 'string', 'exists:pm_tasks,uuid'],
        ]);

        $taskId = $validated['task_id'] ?? null;
        if (!empty($validated['task_uuid'])) {
            $task = PmTask::where('project_id', $project->id)
                ->where('uuid', $validated['task_uuid'])
                ->firstOrFail();
            $taskId = $task->id;
        }

        $comment = PmComment::create([
            'project_id' => $project->id,
            'task_id' => $taskId,
            'user_id' => $user->id,
            'body' => $validated['body'],
        ]);

        PmActivityLog::record(
            $user->id,
            'comment_created',
            'comment',
            $comment->id,
            $project->id,
            [
                'task_id' => $taskId,
                'body_preview' => Str::limit($validated['body'], 100),
            ]
        );

        $taskModel = $task ?? ($taskId ? PmTask::find($taskId) : null);
        $this->notificationService->notifyCommentMentions($comment, $project, $taskModel, $user);

        $comment->load(['author:id,name,email,avatar']);

        return response()->json([
            'success' => true,
            'message' => 'Comment posted successfully.',
            'data' => $comment,
        ], 201);
    }

    /**
     * Delete a comment.
     */
    public function destroy(Request $request, string $projectUuid, int $id): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $project = PmProject::where('uuid', $projectUuid)->firstOrFail();

        if (!$this->accessService->canViewProject($user, $project)) {
            abort(403, 'Unauthorized.');
        }

        $comment = PmComment::where('project_id', $project->id)->findOrFail($id);

        // User must be the comment author OR have project management rights
        if ((int) $comment->user_id !== (int) $user->id && !$this->accessService->canUpdateProject($user, $project)) {
            abort(403, 'You do not have permission to delete this comment.');
        }

        $comment->delete();

        PmActivityLog::record(
            $user->id,
            'comment_deleted',
            'comment',
            $id,
            $project->id
        );

        return response()->json([
            'success' => true,
            'message' => 'Comment deleted successfully.',
        ]);
    }
}
