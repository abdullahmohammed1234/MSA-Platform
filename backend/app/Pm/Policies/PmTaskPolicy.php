<?php

namespace App\Pm\Policies;

use App\Models\User;
use App\Pm\Models\PmTask;
use App\Pm\Services\ProjectAccessService;

class PmTaskPolicy
{
    public function __construct(
        protected ProjectAccessService $accessService
    ) {}

    /**
     * Determine whether the user can view the task.
     */
    public function view(User $user, PmTask $task): bool
    {
        return $this->accessService->canViewProject($user, $task->project);
    }

    /**
     * Determine whether the user can update the task.
     */
    public function update(User $user, PmTask $task): bool
    {
        if (!$this->accessService->canViewProject($user, $task->project)) {
            return false;
        }

        // Project updates or creator/assignee can update task
        if ($this->accessService->canUpdateProject($user, $task->project)) {
            return true;
        }

        return (int) ($task->assigned_to ?? $task->assigned_to_id) === (int) $user->id
            || (int) ($task->created_by ?? $task->created_by_id) === (int) $user->id;
    }

    /**
     * Determine whether the user can delete the task.
     */
    public function delete(User $user, PmTask $task): bool
    {
        if (!$this->accessService->canViewProject($user, $task->project)) {
            return false;
        }

        if ($this->accessService->canUpdateProject($user, $task->project)) {
            return true;
        }

        return (int) ($task->created_by ?? $task->created_by_id) === (int) $user->id;
    }
}
