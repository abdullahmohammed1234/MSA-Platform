<?php

namespace App\Pm\Services;

use App\Models\Department;
use App\Models\User;
use App\Pm\Enums\DepartmentRole;
use App\Pm\Models\PmActivityLog;
use App\Pm\Models\PmDepartmentMembership;
use Illuminate\Database\Eloquent\Collection;

class DepartmentMembershipService
{
    /**
     * Check if a user has an active membership in the given department.
     */
    public function isMember(User $user, int $departmentId): bool
    {
        if ($user->relationLoaded('pmDepartmentMemberships')) {
            return $user->pmDepartmentMemberships->contains(function ($membership) use ($departmentId) {
                return (int) $membership->department_id === (int) $departmentId
                    && $membership->isActive();
            });
        }

        return PmDepartmentMembership::query()
            ->where('user_id', $user->id)
            ->where('department_id', $departmentId)
            ->active()
            ->exists();
    }

    /**
     * Check if a user is an active Lead of the given department.
     */
    public function isLead(User $user, int $departmentId): bool
    {
        if ($user->relationLoaded('pmDepartmentMemberships')) {
            $membership = $user->pmDepartmentMemberships->first(function ($m) use ($departmentId) {
                return (int) $m->department_id === (int) $departmentId
                    && $m->isActive();
            });

            if (!$membership) {
                return false;
            }

            return $membership->department_role === DepartmentRole::Lead || $membership->department_role === 'lead';
        }

        return PmDepartmentMembership::query()
            ->where('user_id', $user->id)
            ->where('department_id', $departmentId)
            ->active()
            ->where('department_role', DepartmentRole::Lead->value)
            ->exists();
    }

    /**
     * Get the user's role in the department if active.
     */
    public function getRole(User $user, int $departmentId): ?DepartmentRole
    {
        $membership = PmDepartmentMembership::query()
            ->where('user_id', $user->id)
            ->where('department_id', $departmentId)
            ->active()
            ->first();

        return $membership?->department_role;
    }

    /**
     * Get all departments where the user holds an active membership.
     *
     * @return Collection<int, Department>
     */
    public function getActiveDepartments(User $user): Collection
    {
        return Department::query()
            ->whereHas('memberships', function ($q) use ($user) {
                $q->where('user_id', $user->id)->active();
            })
            ->get();
    }

    /**
     * Get array of department IDs where the user holds an active membership.
     *
     * @return array<int>
     */
    public function getUserDepartmentIds(User $user): array
    {
        return PmDepartmentMembership::query()
            ->where('user_id', $user->id)
            ->active()
            ->pluck('department_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Add or reactivate a member in a department.
     */
    public function addMember(
        Department $department,
        User $user,
        DepartmentRole $role = DepartmentRole::Member,
        ?User $actor = null
    ): PmDepartmentMembership {
        $membership = PmDepartmentMembership::updateOrCreate(
            [
                'department_id' => $department->id,
                'user_id' => $user->id,
            ],
            [
                'department_role' => $role,
                'status' => 'active',
                'start_date' => now()->toDateString(),
                'end_date' => null,
                'assigned_by' => $actor?->id,
            ]
        );

        PmActivityLog::record(
            actorId: $actor?->id,
            action: 'department_member_added',
            subjectType: 'department',
            subjectId: $department->id,
            metadata: [
                'department_id' => $department->id,
                'department_name' => $department->name,
                'target_user_id' => $user->id,
                'target_user_name' => $user->name,
                'department_role' => $role->value,
            ]
        );

        return $membership;
    }

    /**
     * Remove a member from a department.
     */
    public function removeMember(Department $department, User $user, ?User $actor = null): bool
    {
        $membership = PmDepartmentMembership::query()
            ->where('department_id', $department->id)
            ->where('user_id', $user->id)
            ->first();

        if (!$membership) {
            return false;
        }

        $membership->delete();

        PmActivityLog::record(
            actorId: $actor?->id,
            action: 'department_member_removed',
            subjectType: 'department',
            subjectId: $department->id,
            metadata: [
                'department_id' => $department->id,
                'department_name' => $department->name,
                'target_user_id' => $user->id,
                'target_user_name' => $user->name,
            ]
        );

        return true;
    }

    /**
     * Update a member's role in a department.
     */
    public function updateRole(
        Department $department,
        User $user,
        DepartmentRole $newRole,
        ?User $actor = null
    ): PmDepartmentMembership {
        $membership = PmDepartmentMembership::query()
            ->where('department_id', $department->id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $oldRole = $membership->department_role;
        $membership->department_role = $newRole;
        $membership->save();

        PmActivityLog::record(
            actorId: $actor?->id,
            action: 'department_member_role_updated',
            subjectType: 'department',
            subjectId: $department->id,
            metadata: [
                'department_id' => $department->id,
                'department_name' => $department->name,
                'target_user_id' => $user->id,
                'target_user_name' => $user->name,
                'old_role' => $oldRole instanceof DepartmentRole ? $oldRole->value : $oldRole,
                'new_role' => $newRole->value,
            ]
        );

        return $membership;
    }
}
