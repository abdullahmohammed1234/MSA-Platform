<?php

namespace App\Pm\Policies;

use App\Models\User;
use App\Pm\Models\PmProject;
use App\Pm\Services\ProjectAccessService;
use App\Pm\Support\PmPermissions;

class PmProjectPolicy
{
    public function __construct(
        protected ProjectAccessService $accessService
    ) {}

    /**
     * Determine whether the user can view any projects.
     */
    public function viewAny(User $user): bool
    {
        return $this->accessService->canAccessProjects($user);
    }

    /**
     * Determine whether the user can create projects.
     */
    public function create(User $user): bool
    {
        if (!$this->accessService->canAccessProjects($user)) {
            return false;
        }

        return $user->can(PmPermissions::PROJECTS_CREATE)
            || $user->hasRole('super-admin')
            || $user->hasRole('pms-administrator')
            || $user->pmDepartmentMemberships()->active()->exists();
    }

    /**
     * Determine whether the user can view the project.
     */
    public function view(User $user, PmProject $project): bool
    {
        return $this->accessService->canViewProject($user, $project);
    }

    /**
     * Determine whether the user can update the project.
     */
    public function update(User $user, PmProject $project): bool
    {
        return $this->accessService->canUpdateProject($user, $project);
    }

    /**
     * Determine whether the user can delete the project.
     */
    public function delete(User $user, PmProject $project): bool
    {
        return $this->accessService->canDeleteProject($user, $project);
    }

    /**
     * Determine whether the user can manage project members.
     */
    public function manageMembers(User $user, PmProject $project): bool
    {
        return $this->accessService->canManageMembers($user, $project);
    }

    /**
     * Determine whether the user can create tasks within the project.
     */
    public function createTasks(User $user, PmProject $project): bool
    {
        return $this->accessService->canCreateTask($user, $project);
    }

    /**
     * Determine whether the user can manage participating departments on the project.
     */
    public function manageParticipatingDepartments(User $user, PmProject $project): bool
    {
        return $this->accessService->canManageParticipatingDepartments($user, $project);
    }
}
