<?php

namespace App\Pm\Services;

use App\Models\User;
use App\Pm\Enums\InvitationStatus;
use App\Pm\Enums\ProjectRole;
use App\Pm\Enums\ProjectVisibility;
use App\Pm\Models\PmAttachment;
use App\Pm\Models\PmProject;
use App\Pm\Models\PmProjectMember;
use App\Pm\Models\PmTask;
use App\Pm\Models\PmTaskList;
use App\Services\ApplicationAccessService;

class ProjectAccessService
{
    public function __construct(
        protected ApplicationAccessService $appAccessService,
        protected DepartmentMembershipService $departmentMembershipService
    ) {}

    /**
     * Check if the user has overall application access to the Project Management System.
     */
    public function canAccessProjects(User $user): bool
    {
        return $this->appAccessService->canAccess($user, 'projects');
    }

    /**
     * Determine if a user can view a specific project.
     *
     * SECURITY BOUNDARY (OPTION B):
     * - shared_msa: Any authorized PMS user can view.
     * - department_private: Active members of owning department OR active members of any
     *   participating department OR explicitly accepted project members.
     *   Super-admin, President, and Executive roles DO NOT bypass this check.
     */
    public function canViewProject(User $user, PmProject $project): bool
    {
        if (!$this->canAccessProjects($user)) {
            return false;
        }

        // Shared projects are visible to all authorized PMS users
        if ($project->visibility === ProjectVisibility::SharedMsa) {
            return true;
        }

        // Department-private projects require active membership in owning department OR
        // active membership in any participating department OR accepted explicit invitation
        if ($this->userCanAccessDepartmentPrivateProject($user, $project)) {
            return true;
        }

        return $this->isAcceptedProjectMember($user, $project);
    }

    /**
     * Check if a user can access a department-private project via department memberships (Option B).
     */
    public function userCanAccessDepartmentPrivateProject(User $user, PmProject $project): bool
    {
        // 1. Active membership in owning department
        if ($this->departmentMembershipService->isMember($user, $project->department_id)) {
            return true;
        }

        // 2. Active membership in any participating department
        $participatingDeptIds = $this->getParticipatingDepartmentIds($project);
        if (!empty($participatingDeptIds)) {
            $userDeptIds = $this->departmentMembershipService->getUserDepartmentIds($user);
            if (!empty(array_intersect($participatingDeptIds, $userDeptIds))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get IDs of all participating departments for a project.
     *
     * @return array<int>
     */
    public function getParticipatingDepartmentIds(PmProject $project): array
    {
        if ($project->relationLoaded('participatingDepartments')) {
            return $project->participatingDepartments->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        return $project->participatingDepartments()->pluck('departments.id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * Determine if a user's only basis for viewing is through participating department membership,
     * which defaults to read-only (Viewer equivalent) access.
     */
    public function isParticipatingOnlyViewer(User $user, PmProject $project): bool
    {
        // Explicit project members have their assigned role (not participating-only)
        if ($this->isAcceptedProjectMember($user, $project)) {
            return false;
        }

        // Project designated owner, lead of owning dept, or administrative roles
        if (((int) $project->owner_id === (int) $user->id)
            || $this->departmentMembershipService->isLead($user, $project->department_id)
            || $user->hasRole('super-admin')
            || $user->hasRole('pms-administrator')
        ) {
            return false;
        }

        // Active members of the OWNING department have standard owning department contributor privileges
        if ($this->departmentMembershipService->isMember($user, $project->department_id)) {
            return false;
        }

        // Access is derived solely through participating department (or fallback shared_msa)
        return true;
    }

    /**
     * Determine if a user can edit/update project details, milestones, and metadata.
     */
    public function canUpdateProject(User $user, PmProject $project): bool
    {
        if (!$this->canViewProject($user, $project)) {
            return false;
        }

        // Explicit project membership check takes precedence
        $member = $this->getProjectMembership($user, $project);
        if ($member) {
            if ($member->project_role === ProjectRole::Viewer || $member->project_role === ProjectRole::Contributor) {
                return false;
            }
            if ($member->project_role === ProjectRole::Owner || $member->project_role === ProjectRole::Manager) {
                return true;
            }
        }

        // Designated project owner
        if ($project->owner_id && (int) $project->owner_id === (int) $user->id) {
            return true;
        }

        // Department lead of the owning department
        if ($this->departmentMembershipService->isLead($user, $project->department_id)) {
            return true;
        }

        // Administrative roles CAN update, provided they are already authorized to view
        if ($user->hasRole('super-admin') || $user->hasRole('pms-administrator')) {
            return true;
        }

        return false;
    }

    /**
     * Determine if a user can delete/archive a project.
     * Managers and Contributors CANNOT delete projects. Only Owner, Dept Lead, or Super Admin.
     */
    public function canDeleteProject(User $user, PmProject $project): bool
    {
        if (!$this->canViewProject($user, $project)) {
            return false;
        }

        // Designated project owner
        if ($project->owner_id && (int) $project->owner_id === (int) $user->id) {
            return true;
        }

        // Explicit owner role in members
        $member = $this->getProjectMembership($user, $project);
        if ($member && $member->project_role === ProjectRole::Owner) {
            return true;
        }

        if ($this->departmentMembershipService->isLead($user, $project->department_id)) {
            return true;
        }

        if ($user->hasRole('super-admin') || $user->hasRole('pms-administrator')) {
            return true;
        }

        return false;
    }

    /**
     * Determine if a user can manage project members and invitations.
     */
    public function canManageMembers(User $user, PmProject $project): bool
    {
        return $this->canUpdateProject($user, $project);
    }

    /**
     * Determine if an actor can assign or update a member's role to a target role.
     */
    public function canAssignProjectRole(User $user, PmProject $project, ProjectRole $targetRole, ?PmProjectMember $targetMember = null): bool
    {
        if (!$this->canManageMembers($user, $project)) {
            return false;
        }

        $actorMember = $this->getProjectMembership($user, $project);
        $isOwner = ((int) $project->owner_id === (int) $user->id) || ($actorMember && $actorMember->project_role === ProjectRole::Owner);
        $isAdmin = $user->hasRole('super-admin') || $user->hasRole('pms-administrator');
        $isLead = $this->departmentMembershipService->isLead($user, $project->department_id);

        // Target is existing owner: Only owner or admin can change owner
        if ($targetMember && ($targetMember->project_role === ProjectRole::Owner || (int) $targetMember->user_id === (int) $project->owner_id)) {
            return $isOwner || $isAdmin;
        }

        // Assigning owner role: Only owner, lead, or admin can assign owner
        if ($targetRole === ProjectRole::Owner) {
            return $isOwner || $isLead || $isAdmin;
        }

        // Managers can assign Manager, Contributor, Viewer
        if ($actorMember && $actorMember->project_role === ProjectRole::Manager) {
            return in_array($targetRole, [ProjectRole::Manager, ProjectRole::Contributor, ProjectRole::Viewer], true);
        }

        return $isOwner || $isLead || $isAdmin;
    }

    /**
     * Determine if a user can create tasks within a project.
     * Viewers are STRICTLY read-only and CANNOT create tasks.
     * Members accessing solely through participating departments are read-only Viewers.
     */
    public function canCreateTask(User $user, PmProject $project): bool
    {
        if (!$this->canViewProject($user, $project)) {
            return false;
        }

        $member = $this->getProjectMembership($user, $project);
        if ($member) {
            return $member->project_role !== ProjectRole::Viewer;
        }

        // Department-derived access for participating departments defaults to read-only Viewer
        if ($this->isParticipatingOnlyViewer($user, $project)) {
            return false;
        }

        return true;
    }

    /**
     * Determine if a user can update a task.
     * Viewers are STRICTLY read-only.
     */
    public function canUpdateTask(User $user, PmTask $task): bool
    {
        if (!$this->canViewProject($user, $task->project)) {
            return false;
        }

        $member = $this->getProjectMembership($user, $task->project);
        if ($member) {
            return $member->project_role !== ProjectRole::Viewer;
        }

        // Participating department members without explicit roles are read-only Viewers
        if ($this->isParticipatingOnlyViewer($user, $task->project)) {
            return false;
        }

        return true;
    }

    /**
     * Determine if a user can delete a task.
     * Viewers cannot delete tasks.
     */
    public function canDeleteTask(User $user, PmTask $task): bool
    {
        if (!$this->canViewProject($user, $task->project)) {
            return false;
        }

        $member = $this->getProjectMembership($user, $task->project);
        if ($member && $member->project_role === ProjectRole::Viewer) {
            return false;
        }

        if ($this->isParticipatingOnlyViewer($user, $task->project)) {
            return false;
        }

        if ($this->canUpdateProject($user, $task->project)) {
            return true;
        }

        return (int) $task->created_by === (int) $user->id;
    }

    /**
     * Determine if a user can add a comment to a project.
     * Viewers are STRICTLY read-only.
     */
    public function canAddComment(User $user, PmProject $project): bool
    {
        if (!$this->canViewProject($user, $project)) {
            return false;
        }

        $member = $this->getProjectMembership($user, $project);
        if ($member) {
            return $member->project_role !== ProjectRole::Viewer;
        }

        if ($this->isParticipatingOnlyViewer($user, $project)) {
            return false;
        }

        return true;
    }

    /**
     * Determine if an actor can manage participating departments on a project.
     */
    public function canManageParticipatingDepartments(User $user, PmProject $project): bool
    {
        return $this->canUpdateProject($user, $project);
    }

    /**
     * Check if a user is an accepted member of the project.
     */
    public function isAcceptedProjectMember(User $user, PmProject $project): bool
    {
        if ($project->relationLoaded('members')) {
            return $project->members->contains(function ($m) use ($user) {
                return (int) $m->user_id === (int) $user->id
                    && ($m->invitation_status === InvitationStatus::Accepted || $m->invitation_status === 'accepted');
            });
        }

        return PmProjectMember::query()
            ->where('project_id', $project->id)
            ->where('user_id', $user->id)
            ->accepted()
            ->exists();
    }

    /**
     * Get the user's project membership record if exists.
     */
    public function getProjectMembership(User $user, PmProject $project): ?PmProjectMember
    {
        return PmProjectMember::query()
            ->where('project_id', $project->id)
            ->where('user_id', $user->id)
            ->accepted()
            ->first();
    }

    /**
     * Determine if a user qualifies to be assigned to a task within a project.
     *
     * Rules (Phase 11 Decision 1):
     * - Must be an active PMS user with effective application access.
     * - Must have an explicit project role of Contributor, Manager, or Owner.
     * - Department-level Viewer-equivalent access grant is INSUFFICIENT for task assignment.
     * - Super Admin or Executive status does NOT automatically qualify someone without the required effective project role.
     */
    public function canBeAssignedToTask(User $user, PmProject $project): bool
    {
        // Must be active and have PMS access
        if (!$user->is_active || !$this->canAccessProjects($user)) {
            return false;
        }

        // Designated project owner has implicit explicit Owner role
        if ($project->owner_id && (int) $project->owner_id === (int) $user->id) {
            return true;
        }

        // Check explicit accepted project membership role
        $member = $this->getProjectMembership($user, $project);
        if ($member) {
            return in_array($member->project_role, [
                ProjectRole::Contributor,
                ProjectRole::Manager,
                ProjectRole::Owner,
            ], true);
        }

        // Department-level access or system role without explicit project role is insufficient
        return false;
    }

    /**
     * Retrieve all users eligible for task assignment in the project.
     *
     * @return \Illuminate\Support\Collection<int, User>
     */
    public function getEligibleTaskAssignees(PmProject $project): \Illuminate\Support\Collection
    {
        $eligibleUserIds = collect();

        if ($project->owner_id) {
            $eligibleUserIds->push((int) $project->owner_id);
        }

        $memberUserIds = PmProjectMember::query()
            ->where('project_id', $project->id)
            ->accepted()
            ->whereIn('project_role', [
                ProjectRole::Contributor->value,
                ProjectRole::Manager->value,
                ProjectRole::Owner->value,
            ])
            ->pluck('user_id');

        $eligibleUserIds = $eligibleUserIds->merge($memberUserIds)->unique()->values();

        if ($eligibleUserIds->isEmpty()) {
            return collect();
        }

        return User::query()
            ->whereIn('id', $eligibleUserIds)
            ->where('is_active', true)
            ->get()
            ->filter(fn (User $user) => $this->canAccessProjects($user))
            ->values();
    }

    /**
     * Determine if a user can manage task lists on a project.
     * Creating, renaming, reordering, and deleting lists requires Manager or Owner role.
     */
    public function canManageTaskLists(User $user, PmProject $project): bool
    {
        return $this->canUpdateProject($user, $project);
    }

    /**
     * Determine if a user can upload an attachment to a project or task.
     * Requires Contributor, Manager, or Owner role (Viewers cannot upload).
     */
    public function canUploadAttachment(User $user, PmProject $project): bool
    {
        return $this->canCreateTask($user, $project);
    }

    /**
     * Determine if a user can view or stream download an attachment.
     * Scoped by project visibility under Option B.
     */
    public function canDownloadAttachment(User $user, PmAttachment $attachment): bool
    {
        if (!$attachment->relationLoaded('project')) {
            $attachment->load('project');
        }

        return $this->canViewProject($user, $attachment->project);
    }

    /**
     * Determine if a user can delete an attachment.
     * - Uploader can delete their own attachment.
     * - Project Owner or Manager can delete any attachment in their project.
     * - Others are forbidden.
     */
    public function canDeleteAttachment(User $user, PmAttachment $attachment): bool
    {
        if (!$attachment->relationLoaded('project')) {
            $attachment->load('project');
        }

        if (!$this->canViewProject($user, $attachment->project)) {
            return false;
        }

        // Uploader deletion rule
        if ((int) $attachment->user_id === (int) $user->id) {
            return true;
        }

        // Project Owner or Manager rule
        return $this->canUpdateProject($user, $attachment->project);
    }
}
