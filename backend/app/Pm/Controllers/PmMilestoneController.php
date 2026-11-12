<?php

namespace App\Pm\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Pm\Enums\MilestoneStatus;
use App\Pm\Models\PmActivityLog;
use App\Pm\Models\PmMilestone;
use App\Pm\Models\PmProject;
use App\Pm\Services\PmNotificationService;
use App\Pm\Services\ProjectAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class PmMilestoneController extends Controller
{
    public function __construct(
        protected ProjectAccessService $accessService,
        protected PmNotificationService $notificationService
    ) {}

    /**
     * List milestones for a project.
     */
    public function index(string $uuid): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $project = PmProject::where('uuid', $uuid)->firstOrFail();

        if (!$this->accessService->canViewProject($user, $project)) {
            abort(403, 'Unauthorized.');
        }

        $milestones = PmMilestone::where('project_id', $project->id)
            ->withCount(['tasks', 'tasks as completed_tasks_count' => function ($q) {
                $q->where('status', 'completed');
            }])
            ->orderBy('due_date', 'asc')
            ->get();

        return response()->json([
            'milestones' => $milestones->map(function (PmMilestone $ms) {
                return [
                    'id' => $ms->id,
                    'uuid' => $ms->uuid,
                    'title' => $ms->title,
                    'description' => $ms->description,
                    'status' => $ms->status->value,
                    'status_label' => $ms->status->label(),
                    'due_date' => $ms->due_date?->toDateString(),
                    'tasks_count' => $ms->tasks_count,
                    'completed_tasks_count' => $ms->completed_tasks_count,
                    'completed_at' => $ms->completed_at?->toIso8601String(),
                ];
            }),
        ]);
    }

    /**
     * Create a new milestone within a project.
     */
    public function store(Request $request, string $uuid): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $project = PmProject::where('uuid', $uuid)->firstOrFail();

        if (!$this->accessService->canUpdateProject($user, $project)) {
            abort(403, 'Unauthorized: You do not have permission to add milestones to this project.');
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'due_date' => 'nullable|date',
            'status' => ['nullable', Rule::in(MilestoneStatus::values())],
        ]);

        $milestone = PmMilestone::create([
            'project_id' => $project->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'due_date' => $validated['due_date'] ?? null,
            'status' => isset($validated['status']) ? MilestoneStatus::from($validated['status']) : MilestoneStatus::Pending,
            'created_by' => $user->id,
        ]);

        PmActivityLog::record(
            actorId: $user->id,
            action: 'milestone_created',
            subjectType: 'milestone',
            subjectId: $milestone->id,
            projectId: $project->id,
            metadata: [
                'title' => $milestone->title,
                'due_date' => $milestone->due_date?->toDateString(),
            ]
        );

        return response()->json([
            'message' => 'Milestone created successfully.',
            'milestone' => [
                'id' => $milestone->id,
                'uuid' => $milestone->uuid,
                'title' => $milestone->title,
                'status' => $milestone->status->value,
                'due_date' => $milestone->due_date?->toDateString(),
            ],
        ], 201);
    }

    /**
     * Update an existing milestone.
     */
    public function update(Request $request, string $uuid): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $milestone = PmMilestone::with('project')->where('uuid', $uuid)->firstOrFail();

        if (!$this->accessService->canUpdateProject($user, $milestone->project)) {
            abort(403, 'Unauthorized.');
        }

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'due_date' => 'nullable|date',
            'status' => ['sometimes', 'required', Rule::in(MilestoneStatus::values())],
        ]);

        $oldStatus = $milestone->status;

        if (isset($validated['title'])) {
            $milestone->title = $validated['title'];
        }
        if (array_key_exists('description', $validated)) {
            $milestone->description = $validated['description'];
        }
        if (array_key_exists('due_date', $validated)) {
            $milestone->due_date = $validated['due_date'];
        }
        if (isset($validated['status'])) {
            $milestone->status = MilestoneStatus::from($validated['status']);
            if ($milestone->status === MilestoneStatus::Reached && !$milestone->completed_at) {
                $milestone->completed_at = now();
            }
        }

        $milestone->save();

        PmActivityLog::record(
            actorId: $user->id,
            action: 'milestone_updated',
            subjectType: 'milestone',
            subjectId: $milestone->id,
            projectId: $milestone->project_id,
            metadata: [
                'title' => $milestone->title,
                'status' => $milestone->status->value,
            ]
        );

        if ($milestone->status === MilestoneStatus::Reached && $oldStatus !== MilestoneStatus::Reached) {
            $this->notificationService->notifyMilestoneCompleted($milestone, $milestone->project, $user);
        }

        return response()->json([
            'message' => 'Milestone updated successfully.',
            'milestone' => [
                'uuid' => $milestone->uuid,
                'title' => $milestone->title,
                'status' => $milestone->status->value,
                'due_date' => $milestone->due_date?->toDateString(),
            ],
        ]);
    }

    /**
     * Delete a milestone.
     */
    public function destroy(string $uuid): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $milestone = PmMilestone::with('project')->where('uuid', $uuid)->firstOrFail();

        if (!$this->accessService->canDeleteProject($user, $milestone->project)) {
            abort(403, 'Unauthorized.');
        }

        $title = $milestone->title;
        $projectId = $milestone->project_id;
        $milestone->delete();

        PmActivityLog::record(
            actorId: $user->id,
            action: 'milestone_deleted',
            subjectType: 'milestone',
            subjectId: null,
            projectId: $projectId,
            metadata: [
                'title' => $title,
            ]
        );

        return response()->json([
            'message' => 'Milestone deleted successfully.',
        ]);
    }
}
