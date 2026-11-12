<?php

namespace Tests\Feature\Pm;

use App\Models\Department;
use App\Models\User;
use App\Pm\Enums\DepartmentRole;
use App\Pm\Enums\InvitationStatus;
use App\Pm\Enums\MilestoneStatus;
use App\Pm\Enums\ProjectPriority;
use App\Pm\Enums\ProjectRole;
use App\Pm\Enums\ProjectStatus;
use App\Pm\Enums\ProjectVisibility;
use App\Pm\Enums\TaskStatus;
use App\Pm\Models\PmAccessLog;
use App\Pm\Models\PmActivityLog;
use App\Pm\Models\PmComment;
use App\Pm\Models\PmDepartmentMembership;
use App\Pm\Models\PmMilestone;
use App\Pm\Models\PmProject;
use App\Pm\Models\PmProjectMember;
use App\Pm\Models\PmTask;
use Database\Seeders\PmDepartmentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PmDatabaseFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PmDepartmentSeeder::class);
    }

    public function test_canonical_eight_departments_are_seeded_accurately(): void
    {
        $expected = [
            'presidential' => 'Presidential',
            'marketing' => 'Marketing',
            'finance' => 'Finance',
            'education' => 'Education',
            'graphics' => 'Graphics',
            'events' => 'Events',
            'it' => 'IT',
            'jumuah' => "Jumu'ah",
        ];

        foreach ($expected as $slug => $name) {
            $dept = Department::where('slug', $slug)->first();
            $this->assertNotNull($dept, "Department [{$slug}] should be seeded.");
            $this->assertEquals($name, $dept->name);
            $this->assertTrue($dept->is_active);
            $this->assertNotEmpty($dept->uuid);
            $this->assertNotEmpty($dept->color);
            $this->assertGreaterThan(0, $dept->display_order);
        }

        $this->assertEquals(8, Department::count());
    }

    public function test_projects_can_be_created_with_department_private_visibility(): void
    {
        $user = User::factory()->create();
        $dept = Department::where('slug', 'marketing')->firstOrFail();

        $project = PmProject::create([
            'name' => 'Campus Welcome Campaign',
            'description' => 'Orientation week marketing materials',
            'department_id' => $dept->id,
            'visibility' => ProjectVisibility::DepartmentPrivate,
            'status' => ProjectStatus::Planning,
            'priority' => ProjectPriority::High,
            'owner_id' => $user->id,
            'created_by' => $user->id,
            'start_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
        ]);

        $this->assertDatabaseHas('pm_projects', [
            'id' => $project->id,
            'visibility' => 'department_private',
            'name' => 'Campus Welcome Campaign',
            'department_id' => $dept->id,
            'owner_id' => $user->id,
        ]);

        $this->assertTrue($project->isDepartmentPrivate());
        $this->assertFalse($project->isSharedMsa());
        $this->assertEquals(ProjectVisibility::DepartmentPrivate, $project->visibility);
    }

    public function test_projects_can_be_created_with_shared_msa_visibility(): void
    {
        $user = User::factory()->create();
        $dept = Department::where('slug', 'events')->firstOrFail();

        $project = PmProject::create([
            'name' => 'Annual Islamic Arts Gala',
            'description' => 'Cross-functional annual gala',
            'department_id' => $dept->id,
            'visibility' => ProjectVisibility::SharedMsa,
            'status' => ProjectStatus::InProgress,
            'priority' => ProjectPriority::Urgent,
            'owner_id' => $user->id,
            'created_by' => $user->id,
            'start_date' => now()->toDateString(),
            'due_date' => now()->addDays(60)->toDateString(),
        ]);

        $this->assertDatabaseHas('pm_projects', [
            'id' => $project->id,
            'visibility' => 'shared_msa',
            'name' => 'Annual Islamic Arts Gala',
        ]);

        $this->assertFalse($project->isDepartmentPrivate());
        $this->assertTrue($project->isSharedMsa());
        $this->assertEquals(ProjectVisibility::SharedMsa, $project->visibility);
    }

    public function test_project_visibility_enum_strictly_contains_only_two_approved_values(): void
    {
        $values = ProjectVisibility::values();
        $this->assertCount(2, $values);
        $this->assertContains('department_private', $values);
        $this->assertContains('shared_msa', $values);
        $this->assertNotContains('executive_only', $values);
    }

    public function test_department_membership_tracking_with_roles_and_effective_dates(): void
    {
        $admin = User::factory()->create();
        $member = User::factory()->create();
        $dept = Department::where('slug', 'it')->firstOrFail();

        $membership = PmDepartmentMembership::create([
            'department_id' => $dept->id,
            'user_id' => $member->id,
            'department_role' => DepartmentRole::Coordinator,
            'status' => 'active',
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
            'assigned_by' => $admin->id,
        ]);

        $this->assertDatabaseHas('pm_department_memberships', [
            'id' => $membership->id,
            'department_id' => $dept->id,
            'user_id' => $member->id,
            'department_role' => 'coordinator',
            'status' => 'active',
        ]);

        $this->assertTrue($membership->isActive());
        $this->assertTrue($membership->isCoordinator());
        $this->assertFalse($membership->isLead());

        // Relationship checks
        $this->assertEquals($member->id, $dept->members()->first()->id);
        $this->assertEquals($dept->id, $member->pmDepartments()->first()->id);
    }

    public function test_user_can_have_multiple_department_memberships(): void
    {
        $user = User::factory()->create();
        $itDept = Department::where('slug', 'it')->firstOrFail();
        $eventsDept = Department::where('slug', 'events')->firstOrFail();

        PmDepartmentMembership::create([
            'department_id' => $itDept->id,
            'user_id' => $user->id,
            'department_role' => DepartmentRole::Lead,
            'status' => 'active',
            'start_date' => now()->toDateString(),
        ]);

        PmDepartmentMembership::create([
            'department_id' => $eventsDept->id,
            'user_id' => $user->id,
            'department_role' => DepartmentRole::Member,
            'status' => 'active',
            'start_date' => now()->toDateString(),
        ]);

        $this->assertEquals(2, $user->pmDepartments()->count());
        $this->assertTrue($user->pmDepartments->contains('id', $itDept->id));
        $this->assertTrue($user->pmDepartments->contains('id', $eventsDept->id));
    }

    public function test_project_members_tracks_roles_and_invitation_lifecycle(): void
    {
        $owner = User::factory()->create();
        $collaborator = User::factory()->create();
        $dept = Department::where('slug', 'finance')->firstOrFail();

        $project = PmProject::create([
            'name' => 'Budget Rebalancing Q1',
            'department_id' => $dept->id,
            'visibility' => ProjectVisibility::DepartmentPrivate,
            'owner_id' => $owner->id,
            'created_by' => $owner->id,
        ]);

        // Owner membership
        PmProjectMember::create([
            'project_id' => $project->id,
            'user_id' => $owner->id,
            'project_role' => ProjectRole::Owner,
            'invitation_status' => InvitationStatus::Accepted,
            'accepted_at' => now(),
        ]);

        // Invited collaborator from outside department
        $invitation = PmProjectMember::create([
            'project_id' => $project->id,
            'user_id' => $collaborator->id,
            'project_role' => ProjectRole::Contributor,
            'invitation_status' => InvitationStatus::Pending,
            'invited_by' => $owner->id,
            'invited_at' => now(),
        ]);

        $this->assertTrue($invitation->isPending());
        $this->assertFalse($invitation->isAccepted());

        // Accept invitation
        $invitation->update([
            'invitation_status' => InvitationStatus::Accepted,
            'accepted_at' => now(),
        ]);

        $this->assertTrue($invitation->fresh()->isAccepted());
        $this->assertEquals(2, $project->acceptedMembers()->count());
    }

    public function test_tasks_and_milestones_and_subtasks_relationships(): void
    {
        $user = User::factory()->create();
        $dept = Department::where('slug', 'education')->firstOrFail();

        $project = PmProject::create([
            'name' => 'Spring Halaqah Series',
            'department_id' => $dept->id,
            'visibility' => ProjectVisibility::DepartmentPrivate,
            'owner_id' => $user->id,
            'created_by' => $user->id,
        ]);

        $milestone = PmMilestone::create([
            'project_id' => $project->id,
            'title' => 'Curriculum Finalized',
            'due_date' => now()->addDays(14)->toDateString(),
            'status' => MilestoneStatus::Pending,
            'created_by' => $user->id,
        ]);

        $parentTask = PmTask::create([
            'project_id' => $project->id,
            'milestone_id' => $milestone->id,
            'title' => 'Draft Syllabus',
            'status' => TaskStatus::InProgress,
            'priority' => ProjectPriority::Medium,
            'assigned_to' => $user->id,
            'created_by' => $user->id,
            'due_date' => now()->addDays(7)->toDateString(),
        ]);

        $subtask = PmTask::create([
            'project_id' => $project->id,
            'parent_task_id' => $parentTask->id,
            'title' => 'Review Week 1 Reading List',
            'status' => TaskStatus::Todo,
            'priority' => ProjectPriority::Low,
            'created_by' => $user->id,
        ]);

        $this->assertEquals($milestone->id, $parentTask->milestone->id);
        $this->assertEquals($parentTask->id, $subtask->parentTask->id);
        $this->assertEquals(1, $parentTask->subtasks()->count());
        $this->assertEquals($subtask->id, $parentTask->subtasks->first()->id);
    }

    public function test_comments_and_soft_deletes(): void
    {
        $user = User::factory()->create();
        $dept = Department::where('slug', 'presidential')->firstOrFail();

        $project = PmProject::create([
            'name' => 'Constitution Review',
            'department_id' => $dept->id,
            'visibility' => ProjectVisibility::DepartmentPrivate,
            'owner_id' => $user->id,
            'created_by' => $user->id,
        ]);

        $task = PmTask::create([
            'project_id' => $project->id,
            'title' => 'Review Bylaw Section 4',
            'created_by' => $user->id,
        ]);

        $comment = PmComment::create([
            'project_id' => $project->id,
            'task_id' => $task->id,
            'user_id' => $user->id,
            'body' => 'Bylaw draft section 4 wording looks solid.',
        ]);

        $this->assertDatabaseHas('pm_comments', ['id' => $comment->id]);

        $comment->delete();
        $this->assertSoftDeleted('pm_comments', ['id' => $comment->id]);

        $task->delete();
        $this->assertSoftDeleted('pm_tasks', ['id' => $task->id]);

        $project->delete();
        $this->assertSoftDeleted('pm_projects', ['id' => $project->id]);
    }

    public function test_activity_and_access_logs_record_changes_and_json_metadata(): void
    {
        $admin = User::factory()->create();
        $targetUser = User::factory()->create();
        $dept = Department::where('slug', 'it')->firstOrFail();

        $project = PmProject::create([
            'name' => 'Server Migration',
            'department_id' => $dept->id,
            'visibility' => ProjectVisibility::SharedMsa,
            'owner_id' => $admin->id,
            'created_by' => $admin->id,
        ]);

        // Project activity log
        $activity = PmActivityLog::create([
            'project_id' => $project->id,
            'user_id' => $admin->id,
            'action' => 'project_created',
            'entity_type' => 'PmProject',
            'entity_id' => $project->id,
            'metadata' => [
                'name' => $project->name,
                'visibility' => $project->visibility->value,
            ],
        ]);

        $this->assertDatabaseHas('pm_activity_logs', [
            'id' => $activity->id,
            'action' => 'project_created',
        ]);
        $this->assertEquals('server-migration', Str::slug($activity->metadata['name']));

        // Administrative access log
        $accessLog = PmAccessLog::create([
            'target_user_id' => $targetUser->id,
            'actor_user_id' => $admin->id,
            'action' => 'grant_pms_access',
            'previous_value' => ['has_access' => false],
            'new_value' => ['has_access' => true, 'role' => 'coordinator'],
            'reason' => 'Appointed IT Coordinator for Fall 2026',
        ]);

        $this->assertDatabaseHas('pm_access_logs', [
            'id' => $accessLog->id,
            'action' => 'grant_pms_access',
        ]);
        $this->assertTrue($accessLog->new_value['has_access']);
        $this->assertEquals('coordinator', $accessLog->new_value['role']);
    }

    public function test_jumuah_department_membership_creation_and_deactivation(): void
    {
        $jumuah = Department::where('slug', 'jumuah')->firstOrFail();
        $user = User::factory()->create(['name' => 'Jumuah Lead']);

        // Create membership
        $membership = PmDepartmentMembership::create([
            'department_id' => $jumuah->id,
            'user_id' => $user->id,
            'department_role' => DepartmentRole::Lead,
            'status' => 'active',
            'start_date' => now()->toDateString(),
        ]);

        $this->assertDatabaseHas('pm_department_memberships', [
            'id' => $membership->id,
            'department_id' => $jumuah->id,
            'user_id' => $user->id,
            'department_role' => DepartmentRole::Lead->value,
            'status' => 'active',
        ]);

        // Deactivate membership
        $membership->update(['status' => 'inactive', 'end_date' => now()->toDateString()]);

        $this->assertDatabaseHas('pm_department_memberships', [
            'id' => $membership->id,
            'status' => 'inactive',
        ]);
        // Historical record preserved
        $this->assertEquals(1, PmDepartmentMembership::where('user_id', $user->id)->count());
    }

    public function test_jumuah_project_creation_and_ownership(): void
    {
        $jumuah = Department::where('slug', 'jumuah')->firstOrFail();
        $lead = User::factory()->create(['name' => 'Khateeb Coordinator']);

        $project = PmProject::create([
            'name' => 'Burnaby Hall Khateeb Schedule Fall 2026',
            'department_id' => $jumuah->id,
            'visibility' => ProjectVisibility::DepartmentPrivate,
            'status' => ProjectStatus::InProgress,
            'priority' => ProjectPriority::Urgent,
            'owner_id' => $lead->id,
            'created_by' => $lead->id,
        ]);

        $this->assertDatabaseHas('pm_projects', [
            'id' => $project->id,
            'department_id' => $jumuah->id,
            'owner_id' => $lead->id,
            'visibility' => ProjectVisibility::DepartmentPrivate->value,
        ]);

        $this->assertEquals("Jumu'ah", $project->department->name);
        $this->assertEquals($lead->id, $project->owner->id);
    }

    public function test_deactivating_department_preserves_historical_records(): void
    {
        $jumuah = Department::where('slug', 'jumuah')->firstOrFail();
        $user = User::factory()->create();

        $project = PmProject::create([
            'name' => 'Surrey Jumuah Sound System Upgrade',
            'department_id' => $jumuah->id,
            'visibility' => ProjectVisibility::SharedMsa,
            'status' => ProjectStatus::Planning,
            'priority' => ProjectPriority::Medium,
            'owner_id' => $user->id,
            'created_by' => $user->id,
        ]);

        PmDepartmentMembership::create([
            'department_id' => $jumuah->id,
            'user_id' => $user->id,
            'department_role' => DepartmentRole::Member,
            'status' => 'active',
            'start_date' => now()->toDateString(),
        ]);

        // Deactivate department
        $jumuah->update(['is_active' => false]);

        $this->assertDatabaseHas('departments', [
            'id' => $jumuah->id,
            'is_active' => false,
        ]);

        // Projects and memberships remain intact
        $this->assertDatabaseHas('pm_projects', ['id' => $project->id]);
        $this->assertDatabaseHas('pm_department_memberships', ['department_id' => $jumuah->id, 'user_id' => $user->id]);
    }
}
