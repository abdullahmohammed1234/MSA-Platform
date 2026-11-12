<?php

namespace App\Pm\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\User;
use App\Pm\Models\PmProject;
use App\Pm\Services\DepartmentMembershipService;
use App\Pm\Services\PmWorkloadService;
use App\Pm\Services\ProjectAccessService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PmWorkloadController extends Controller
{
    public function __construct(
        protected ProjectAccessService $accessService,
        protected DepartmentMembershipService $departmentMembershipService,
        protected PmWorkloadService $workloadService
    ) {}

    /**
     * Get workload for all eligible team members of a project.
     */
    public function projectWorkload(Request $request, string $uuid): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $project = PmProject::where('uuid', $uuid)->firstOrFail();

        if (!$this->accessService->canViewProject($user, $project)) {
            abort(403, 'Unauthorized.');
        }

        $startDate = $request->query('start_date') ?: Carbon::now()->startOfWeek()->toDateString();
        $endDate = $request->query('end_date') ?: Carbon::parse($startDate)->addDays(28)->toDateString();

        $workload = $this->workloadService->getProjectTeamWorkload(
            project: $project,
            startDate: $startDate,
            endDate: $endDate,
            viewer: $user
        );

        return response()->json([
            'status' => 'success',
            'data' => $workload,
        ]);
    }

    /**
     * Get department-wide workload across all active department members.
     * Restricted to Department Leads and PMS Administrators.
     */
    public function departmentWorkload(Request $request, int $departmentId): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $department = Department::findOrFail($departmentId);

        $isLead = $this->departmentMembershipService->isLead($user, $departmentId);
        $isAdmin = $user->hasRole('super-admin') || $user->hasRole('pms-administrator');

        if (!$isLead && !$isAdmin) {
            abort(403, 'Unauthorized. Department Lead or Administrator access required.');
        }

        $startDate = $request->query('start_date') ?: Carbon::now()->startOfWeek()->toDateString();
        $endDate = $request->query('end_date') ?: Carbon::parse($startDate)->addDays(28)->toDateString();

        $members = User::query()
            ->whereHas('pmDepartmentMemberships', function ($q) use ($departmentId) {
                $q->where('department_id', $departmentId)->active();
            })
            ->where('is_active', true)
            ->get();

        $membersWorkload = [];
        foreach ($members as $member) {
            $membersWorkload[] = $this->workloadService->getMemberWorkload(
                member: $member,
                startDate: $startDate,
                endDate: $endDate,
                viewer: $user,
                projectScope: null
            );
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'department' => [
                    'id' => $department->id,
                    'name' => $department->name,
                    'slug' => $department->slug,
                ],
                'date_range' => [
                    'start' => $startDate,
                    'end' => $endDate,
                ],
                'members_workload' => $membersWorkload,
            ],
        ]);
    }
}

