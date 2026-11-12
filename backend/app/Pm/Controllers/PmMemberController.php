<?php

namespace App\Pm\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Pm\Enums\InvitationStatus;
use App\Pm\Enums\ProjectRole;
use App\Pm\Models\PmActivityLog;
use App\Pm\Models\PmProject;
use App\Pm\Models\PmProjectMember;
use App\Pm\Services\ProjectAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class PmMemberController extends Controller
{
    public function __construct(
        protected ProjectAccessService $accessService
    ) {}

    /**
     * List members and active invitations for a project.
     */
    public function index(string $uuid): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $project = PmProject::where('uuid', $uuid)->firstOrFail();

        if (!$this->accessService->canViewProject($user, $project)) {
            abort(403, 'Unauthorized.');
        }

        $members = PmProjectMember::with(['user', 'inviter'])
            ->where('project_id', $project->id)
            ->get();

        return response()->json([
            'members' => $members->map(function (PmProjectMember $m) {
                return [
                    'id' => $m->id,
                    'user' => [
                        'id' => $m->user?->id,
                        'name' => $m->user?->name,
                        'email' => $m->user?->email,
                        'avatar' => $m->user?->avatar,
                    ],
                    'project_role' => $m->project_role->value,
                    'project_role_label' => $m->project_role->label(),
                    'invitation_status' => $m->invitation_status->value,
                    'invited_by' => $m->inviter ? [
                        'id' => $m->inviter->id,
                        'name' => $m->inviter->name,
                    ] : null,
                    'invited_at' => $m->invited_at?->toIso8601String(),
                    'accepted_at' => $m->accepted_at?->toIso8601String(),
                ];
            }),
        ]);
    }

    /**
     * Invite a user to collaborate on a project.
     */
    public function invite(Request $request, string $uuid): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $project = PmProject::where('uuid', $uuid)->firstOrFail();

        if (!$this->accessService->canManageMembers($user, $project)) {
            abort(403, 'Unauthorized: You do not have permission to manage members for this project.');
        }

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'project_role' => ['required', Rule::in(ProjectRole::values())],
        ]);

        $targetUser = User::findOrFail($validated['user_id']);

        // Check if member already exists
        $existing = PmProjectMember::where('project_id', $project->id)
            ->where('user_id', $targetUser->id)
            ->first();

        if ($existing && $existing->invitation_status === InvitationStatus::Accepted) {
            return response()->json([
                'message' => "{$targetUser->name} is already an accepted member of this project.",
            ], 422);
        }

        $membership = PmProjectMember::updateOrCreate(
            [
                'project_id' => $project->id,
                'user_id' => $targetUser->id,
            ],
            [
                'project_role' => ProjectRole::from($validated['project_role']),
                'invitation_status' => InvitationStatus::Pending,
                'invited_by' => $user->id,
                'invited_at' => now(),
            ]
        );

        PmActivityLog::record(
            actorId: $user->id,
            action: 'project_member_invited',
            subjectType: 'project_member',
            subjectId: $membership->id,
            projectId: $project->id,
            metadata: [
                'target_user_id' => $targetUser->id,
                'target_user_name' => $targetUser->name,
                'project_role' => $membership->project_role->value,
            ]
        );

        return response()->json([
            'message' => "Invitation sent to {$targetUser->name}.",
            'member' => [
                'id' => $membership->id,
                'user_id' => $targetUser->id,
                'project_role' => $membership->project_role->value,
                'invitation_status' => $membership->invitation_status->value,
            ],
        ], 201);
    }

    /**
     * Accept a project invitation.
     */
    public function acceptInvitation(int $memberId): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $membership = PmProjectMember::with('project')->findOrFail($memberId);

        if ((int) $membership->user_id !== (int) $user->id) {
            abort(403, 'Unauthorized: You can only respond to your own invitations.');
        }

        $membership->invitation_status = InvitationStatus::Accepted;
        $membership->accepted_at = now();
        $membership->save();

        PmActivityLog::record(
            actorId: $user->id,
            action: 'project_invitation_accepted',
            subjectType: 'project_member',
            subjectId: $membership->id,
            projectId: $membership->project_id,
            metadata: [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'project_name' => $membership->project?->name,
            ]
        );

        return response()->json([
            'message' => 'Project invitation accepted.',
            'project' => [
                'uuid' => $membership->project?->uuid,
                'name' => $membership->project?->name,
            ],
        ]);
    }

    /**
     * Decline a project invitation.
     */
    public function declineInvitation(int $memberId): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $membership = PmProjectMember::findOrFail($memberId);

        if ((int) $membership->user_id !== (int) $user->id) {
            abort(403, 'Unauthorized: You can only respond to your own invitations.');
        }

        $membership->delete();

        return response()->json([
            'message' => 'Project invitation declined.',
        ]);
    }

    /**
     * Remove or revoke a project member.
     */
    public function removeMember(string $uuid, int $memberId): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $project = PmProject::where('uuid', $uuid)->firstOrFail();

        if (!$this->accessService->canManageMembers($user, $project)) {
            abort(403, 'Unauthorized: You do not have permission to manage members for this project.');
        }

        $membership = PmProjectMember::where('project_id', $project->id)
            ->where('id', $memberId)
            ->firstOrFail();

        // Project owner cannot be removed
        if ((int) $membership->user_id === (int) $project->owner_id) {
            return response()->json([
                'message' => 'The designated project owner cannot be removed from the project.',
            ], 422);
        }

        $targetUserName = $membership->user?->name;
        $membership->delete();

        PmActivityLog::record(
            actorId: $user->id,
            action: 'project_member_removed',
            subjectType: 'project_member',
            subjectId: $memberId,
            projectId: $project->id,
            metadata: [
                'target_user_name' => $targetUserName,
            ]
        );

        return response()->json([
            'message' => 'Project member removed successfully.',
        ]);
    }

    /**
     * Update a project member's role (Owner, Manager, Contributor, Viewer).
     */
    public function updateMemberRole(Request $request, string $uuid, int $memberId): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $project = PmProject::where('uuid', $uuid)->firstOrFail();

        $validated = $request->validate([
            'project_role' => ['required', Rule::in(ProjectRole::values())],
        ]);

        $membership = PmProjectMember::where('project_id', $project->id)
            ->where('id', $memberId)
            ->firstOrFail();

        $targetRole = ProjectRole::from($validated['project_role']);

        if (!$this->accessService->canAssignProjectRole($user, $project, $targetRole, $membership)) {
            abort(403, 'Unauthorized: Insufficient privileges to assign this project role.');
        }

        $oldRole = $membership->project_role;
        $membership->project_role = $targetRole;
        $membership->save();

        PmActivityLog::record(
            actorId: $user->id,
            action: 'project_member_role_updated',
            subjectType: 'project_member',
            subjectId: $membership->id,
            projectId: $project->id,
            metadata: [
                'target_user_id' => $membership->user_id,
                'target_user_name' => $membership->user?->name,
                'old_role' => $oldRole->value,
                'new_role' => $targetRole->value,
            ]
        );

        return response()->json([
            'message' => "Project role updated to {$targetRole->label()}.",
            'member' => [
                'id' => $membership->id,
                'user_id' => $membership->user_id,
                'project_role' => $membership->project_role->value,
                'project_role_label' => $membership->project_role->label(),
                'invitation_status' => $membership->invitation_status->value,
            ],
        ]);
    }

    /**
     * Get all pending project invitations for the current user.
     */
    public function myInvitations(): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $invitations = PmProjectMember::with(['project.department', 'inviter:id,name,email'])
            ->where('user_id', $user->id)
            ->where('invitation_status', InvitationStatus::Pending)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $invitations,
        ]);
    }
}
