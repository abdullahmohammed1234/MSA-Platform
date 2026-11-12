<?php

namespace App\Pm\Policies;

use App\Models\User;
use App\Pm\Models\PmMilestone;
use App\Pm\Services\ProjectAccessService;

class PmMilestonePolicy
{
    public function __construct(
        protected ProjectAccessService $accessService
    ) {}

    /**
     * Determine whether the user can view the milestone.
     */
    public function view(User $user, PmMilestone $milestone): bool
    {
        return $this->accessService->canViewProject($user, $milestone->project);
    }

    /**
     * Determine whether the user can update the milestone.
     */
    public function update(User $user, PmMilestone $milestone): bool
    {
        return $this->accessService->canUpdateProject($user, $milestone->project);
    }

    /**
     * Determine whether the user can delete the milestone.
     */
    public function delete(User $user, PmMilestone $milestone): bool
    {
        return $this->accessService->canDeleteProject($user, $milestone->project);
    }
}
