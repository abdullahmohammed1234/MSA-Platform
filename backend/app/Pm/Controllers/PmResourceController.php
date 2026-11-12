<?php

namespace App\Pm\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Pm\Models\PmProject;
use App\Pm\Models\PmUserCapacity;
use App\Pm\Services\DepartmentMembershipService;
use App\Pm\Services\PmNotificationService;
use App\Pm\Services\PmResourceBalancingService;
use App\Pm\Services\ProjectAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PmResourceController extends Controller
{
    public function __construct(
        protected ProjectAccessService $accessService,
        protected DepartmentMembershipService $departmentMembershipService,
        protected PmResourceBalancingService $balancingService
    ) {}

    /**
     * List user capacity profiles, optionally filtered by department or user IDs.
     */
    public function indexCapacities(Request $request): JsonResponse
    {
        try {
            $query = PmUserCapacity::with(['user:id,name,email,avatar']);

            if ($request->has('department_id')) {
                $deptId = (int) $request->query('department_id');
                $query->whereHas('user.pmDepartmentMemberships', function ($q) use ($deptId) {
                    $q->where('department_id', $deptId)->active();
                });
            }

            if ($request->has('user_ids')) {
                $userIds = (array) $request->query('user_ids');
                $query->whereIn('user_id', $userIds);
            }

            $capacities = $query->get();
        } catch (\Throwable $e) {
            $capacities = collect();
        }

        return response()->json([
            'status' => 'success',
            'data' => $capacities,
        ]);
    }

    /**
     * Show a user's capacity profile or default fallback.
     */
    public function showCapacity(Request $request, int $userId): JsonResponse
    {
        /** @var User $currentUser */
        $currentUser = Auth::user();

        $targetUser = User::findOrFail($userId);

        $isSelf = (int) $currentUser->id === (int) $targetUser->id;
        $isAdmin = $currentUser->hasRole('super-admin') || $currentUser->hasRole('pms-administrator');
        $isLead = false;

        if (!$isSelf && !$isAdmin) {
            $targetDepts = $this->departmentMembershipService->getUserDepartmentIds($targetUser);
            foreach ($targetDepts as $deptId) {
                if ($this->departmentMembershipService->isLead($currentUser, $deptId)) {
                    $isLead = true;
                    break;
                }
            }
        }

        if (!$isSelf && !$isAdmin && !$isLead) {
            abort(403, 'Unauthorized to view this capacity profile.');
        }

        try {
            $capacity = PmUserCapacity::where('user_id', $userId)->first();
        } catch (\Throwable $e) {
            $capacity = null;
        }

        if (!$capacity) {
            return response()->json([
                'status' => 'success',
                'data' => [
                    'user_id' => $targetUser->id,
                    'weekly_capacity_hours' => (float) config('pm.capacity.default_weekly_hours', 10.0),
                    'daily_capacity_hours' => (float) config('pm.capacity.default_daily_hours', 2.0),
                    'daily_availability' => null,
                    'max_concurrent_tasks' => (int) config('pm.capacity.default_max_concurrent_tasks', 5),
                    'effective_from' => null,
                    'effective_to' => null,
                    'notes' => null,
                    'is_default' => true,
                ],
            ]);
        }

        return response()->json([
            'status' => 'success',
            'data' => $capacity,
        ]);
    }

    /**
     * Create or update a user's capacity configuration.
     */
    public function updateCapacity(Request $request, int $userId): JsonResponse
    {
        /** @var User $currentUser */
        $currentUser = Auth::user();

        $targetUser = User::findOrFail($userId);

        $isSelf = (int) $currentUser->id === (int) $targetUser->id;
        $isAdmin = $currentUser->hasRole('super-admin') || $currentUser->hasRole('pms-administrator');
        $isLead = false;

        if (!$isSelf && !$isAdmin) {
            $targetDepts = $this->departmentMembershipService->getUserDepartmentIds($targetUser);
            foreach ($targetDepts as $deptId) {
                if ($this->departmentMembershipService->isLead($currentUser, $deptId)) {
                    $isLead = true;
                    break;
                }
            }
        }

        if (!$isSelf && !$isAdmin && !$isLead) {
            abort(403, 'Unauthorized to modify this capacity profile.');
        }

        $validated = $request->validate([
            'weekly_capacity_hours' => 'nullable|numeric|min:0|max:168',
            'daily_capacity_hours' => 'nullable|numeric|min:0|max:24',
            'daily_availability' => 'nullable|array',
            'max_concurrent_tasks' => 'nullable|integer|min:1|max:50',
            'effective_from' => 'nullable|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
            'notes' => 'nullable|string|max:500',
        ]);

        $capacity = PmUserCapacity::updateOrCreate(
            ['user_id' => $targetUser->id],
            [
                'weekly_capacity_hours' => $validated['weekly_capacity_hours'] ?? (float) config('pm.capacity.default_weekly_hours', 10.0),
                'daily_capacity_hours' => $validated['daily_capacity_hours'] ?? (float) config('pm.capacity.default_daily_hours', 2.0),
                'daily_availability' => $validated['daily_availability'] ?? null,
                'max_concurrent_tasks' => $validated['max_concurrent_tasks'] ?? (int) config('pm.capacity.default_max_concurrent_tasks', 5),
                'effective_from' => $validated['effective_from'] ?? null,
                'effective_to' => $validated['effective_to'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'updated_by' => $currentUser->id,
            ]
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Capacity profile updated successfully.',
            'data' => $capacity,
        ]);
    }

    /**
     * Get advisory resource balancing suggestions for a project.
     */
    public function balancingSuggestions(Request $request, string $uuid): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $project = PmProject::where('uuid', $uuid)->firstOrFail();

        if (!$this->accessService->canViewProject($user, $project)) {
            abort(403, 'Unauthorized.');
        }

        $suggestions = $this->balancingService->getBalancingSuggestions($project, $user);

        return response()->json([
            'status' => 'success',
            'data' => $suggestions,
        ]);
    }

    /**
     * Apply an advisory balancing suggestion to reassign a task.
     */
    public function applyBalancing(Request $request, string $uuid): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $project = PmProject::where('uuid', $uuid)->firstOrFail();

        $validated = $request->validate([
            'task_uuid' => 'required|string',
            'source_user_id' => 'required|integer',
            'target_user_id' => 'required|integer',
        ]);

        $updatedTask = $this->balancingService->applyBalancingSuggestion(
            project: $project,
            taskUuid: $validated['task_uuid'],
            sourceUserId: (int) $validated['source_user_id'],
            targetUserId: (int) $validated['target_user_id'],
            actor: $user
        );

        app(PmNotificationService::class)->notifyTaskAssigned(
            $updatedTask,
            [(int) $validated['target_user_id']],
            $user,
            true
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Task reassigned successfully.',
            'data' => $updatedTask,
        ]);
    }
}

