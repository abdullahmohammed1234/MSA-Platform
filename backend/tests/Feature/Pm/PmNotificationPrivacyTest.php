<?php

namespace Tests\Feature\Pm;

use App\Models\Department;
use App\Models\Notification as NotificationModel;
use App\Models\Role;
use App\Models\User;
use App\Pm\Enums\ProjectPriority;
use App\Pm\Enums\ProjectRole;
use App\Pm\Enums\ProjectStatus;
use App\Pm\Enums\ProjectVisibility;
use App\Pm\Enums\TaskStatus;
use App\Pm\Models\PmProject;
use App\Pm\Models\PmProjectMember;
use App\Pm\Models\PmTask;
use App\Pm\Notifications\TaskAssignedNotification;
use App\Services\ApplicationAccessService;
use App\Pm\Services\DepartmentMembershipService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PmNotificationPrivacyTest extends TestCase
{
    use RefreshDatabase;

    protected User $member;
    protected User $outsider;
    protected User $superAdmin;
    protected Department $departmentA;
    protected Department $departmentB;
    protected PmProject $projectA;
    protected PmProject $projectB;
    protected PmTask $taskA;
    protected PmTask $taskB;

    protected function setUp(): void
    {
        parent::setUp();

        $appAccess = app(ApplicationAccessService::class);

        $superAdminRole = Role::firstOrCreate(
            ['slug' => 'super-admin'],
            ['uuid' => (string) Str::uuid(), 'name' => 'Super Admin']
        );

        $this->member = User::factory()->create(['name' => 'Alice Member', 'email' => 'alice@sfu.ca']);
        $this->outsider = User::factory()->create(['name' => 'Charlie Outsider', 'email' => 'charlie@sfu.ca']);
        $this->superAdmin = User::factory()->create(['name' => 'Super Admin', 'email' => 'admin@sfu.ca']);
        $this->superAdmin->roles()->sync([$superAdminRole->id]);

        $appAccess->grant($this->member, 'projects');
        $appAccess->grant($this->outsider, 'projects');
        $appAccess->grant($this->superAdmin, 'projects');

        $this->departmentA = Department::create(['name' => 'Department A', 'code' => 'DEPT-A']);
        $this->departmentB = Department::create(['name' => 'Department B', 'code' => 'DEPT-B']);

        // Project A: Department A private
        $this->projectA = PmProject::create([
            'name' => 'Confidential Alpha Project',
            'slug' => 'confidential-alpha',
            'visibility' => ProjectVisibility::DepartmentPrivate,
            'department_id' => $this->departmentA->id,
            'owner_id' => $this->member->id,
            'status' => ProjectStatus::InProgress,
            'priority' => ProjectPriority::High,
            'created_by' => $this->member->id,
        ]);

        // Project B: Department B private
        $this->projectB = PmProject::create([
            'name' => 'Bravo Project',
            'slug' => 'bravo-project',
            'visibility' => ProjectVisibility::DepartmentPrivate,
            'department_id' => $this->departmentB->id,
            'owner_id' => $this->outsider->id,
            'status' => ProjectStatus::InProgress,
            'priority' => ProjectPriority::Medium,
            'created_by' => $this->outsider->id,
        ]);

        // Member is active in Department A and has project membership in Project A
        $deptService = app(DepartmentMembershipService::class);
        $deptService->addMember($this->departmentA, $this->member);

        $this->taskA = PmTask::create([
            'project_id' => $this->projectA->id,
            'title' => 'Secret Strategic Audit',
            'status' => TaskStatus::InProgress,
            'priority' => ProjectPriority::High,
            'assigned_to' => $this->member->id,
            'created_by' => $this->member->id,
            'due_date' => Carbon::now()->addDays(3),
        ]);

        $this->taskB = PmTask::create([
            'project_id' => $this->projectB->id,
            'title' => 'Bravo Task',
            'status' => TaskStatus::InProgress,
            'priority' => ProjectPriority::Medium,
            'assigned_to' => $this->outsider->id,
            'created_by' => $this->outsider->id,
        ]);
    }

    #[Test]
    public function historical_notification_is_visible_while_user_retains_access(): void
    {
        // Create in-app notification for Member in Project A
        $notification = NotificationModel::create([
            'user_id' => $this->member->id,
            'type' => TaskAssignedNotification::class,
            'title' => "Task Assigned: {$this->taskA->title}",
            'message' => "You have been assigned to task '{$this->taskA->title}' in project '{$this->projectA->name}'.",
            'data' => [
                'type' => 'pm.task_assigned',
                'project_id' => $this->projectA->id,
                'task_id' => $this->taskA->id,
            ],
            'read_at' => null,
        ]);

        $response = $this->actingAs($this->member)->getJson('/api/v1/notifications');
        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertCount(1, $data);
        $this->assertEquals("Task Assigned: {$this->taskA->title}", $data[0]['title']);

        // Check unread count
        $unreadRes = $this->actingAs($this->member)->getJson('/api/v1/notifications/unread');
        $unreadRes->assertStatus(200);
        $this->assertEquals(1, $unreadRes->json('unread_count'));
    }

    #[Test]
    public function historical_notification_becomes_inaccessible_after_project_access_revocation(): void
    {
        // 1. Create in-app notification for Member in Project A
        $notification = NotificationModel::create([
            'user_id' => $this->member->id,
            'type' => TaskAssignedNotification::class,
            'title' => "Task Assigned: {$this->taskA->title}",
            'message' => "You have been assigned to task '{$this->taskA->title}' in project '{$this->projectA->name}'.",
            'data' => [
                'type' => 'pm.task_assigned',
                'project_id' => $this->projectA->id,
                'task_id' => $this->taskA->id,
            ],
            'read_at' => null,
        ]);

        // 2. Revoke user from Department A and remove project membership
        $deptService = app(DepartmentMembershipService::class);
        $deptService->removeMember($this->departmentA, $this->member);
        PmProjectMember::where('project_id', $this->projectA->id)->where('user_id', $this->member->id)->delete();
        $this->projectA->update(['owner_id' => $this->outsider->id, 'created_by' => $this->outsider->id]);

        // 3. Member retrieves notifications list: must be SUPPRESSED
        $response = $this->actingAs($this->member)->getJson('/api/v1/notifications');
        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(0, $data, 'Inaccessible project notifications must be excluded from listing');

        // 4. Unread count must be 0
        $unreadRes = $this->actingAs($this->member)->getJson('/api/v1/notifications/unread');
        $unreadRes->assertStatus(200);
        $this->assertEquals(0, $unreadRes->json('unread_count'));

        // 5. Database record must still exist (auditability)
        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
            'user_id' => $this->member->id,
        ]);

        // 6. Direct mark-as-read must return 403 Forbidden under Option B
        $markReadRes = $this->actingAs($this->member)->postJson("/api/v1/notifications/{$notification->id}/read");
        $markReadRes->assertStatus(403);
    }

    #[Test]
    public function super_admin_and_executives_cannot_view_confidential_notifications_without_membership(): void
    {
        // Create notification assigned to superAdmin for confidential Project A
        // where superAdmin has NO department or project membership
        $notification = NotificationModel::create([
            'user_id' => $this->superAdmin->id,
            'type' => TaskAssignedNotification::class,
            'title' => "Task Assigned: {$this->taskA->title}",
            'message' => "You have been assigned to task '{$this->taskA->title}' in project '{$this->projectA->name}'.",
            'data' => [
                'type' => 'pm.task_assigned',
                'project_id' => $this->projectA->id,
                'task_id' => $this->taskA->id,
            ],
            'read_at' => null,
        ]);

        // SuperAdmin should NOT see confidential Project A notification
        $response = $this->actingAs($this->superAdmin)->getJson('/api/v1/notifications');
        $response->assertStatus(200);
        $this->assertCount(0, $response->json('data'));

        $unreadRes = $this->actingAs($this->superAdmin)->getJson('/api/v1/notifications/unread');
        $this->assertEquals(0, $unreadRes->json('unread_count'));

        // Direct mark-as-read must be forbidden
        $readRes = $this->actingAs($this->superAdmin)->postJson("/api/v1/notifications/{$notification->id}/read");
        $readRes->assertStatus(403);
    }

    #[Test]
    public function non_pms_notifications_remain_completely_unaffected(): void
    {
        // Create non-PMS notification (e.g. course completion / store update)
        $nonPmNotification = NotificationModel::create([
            'user_id' => $this->member->id,
            'type' => 'App\\Notifications\\CourseCompletedNotification',
            'title' => 'Course Completed: Islamic History 101',
            'message' => 'Congratulations on completing your course certificate!',
            'data' => [
                'type' => 'academy.course_completed',
                'course_id' => 99,
            ],
            'read_at' => null,
        ]);

        $response = $this->actingAs($this->member)->getJson('/api/v1/notifications');
        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertCount(1, $data);
        $this->assertEquals('Course Completed: Islamic History 101', $data[0]['title']);

        $unreadRes = $this->actingAs($this->member)->getJson('/api/v1/notifications/unread');
        $this->assertEquals(1, $unreadRes->json('unread_count'));

        // Mark as read succeeds
        $markRes = $this->actingAs($this->member)->postJson("/api/v1/notifications/{$nonPmNotification->id}/read");
        $markRes->assertStatus(200);
        $this->assertNotNull($nonPmNotification->fresh()->read_at);
    }

    #[Test]
    public function notifications_for_other_accessible_projects_remain_visible(): void
    {
        // Member is active in Department A (Project A) and invited to Project B
        PmProjectMember::create([
            'project_id' => $this->projectB->id,
            'user_id' => $this->member->id,
            'project_role' => ProjectRole::Contributor,
            'invitation_status' => 'accepted',
        ]);

        // Notification for Project A
        $notifA = NotificationModel::create([
            'user_id' => $this->member->id,
            'type' => TaskAssignedNotification::class,
            'title' => "Task Assigned: {$this->taskA->title}",
            'message' => "Project A task",
            'data' => ['type' => 'pm.task_assigned', 'project_id' => $this->projectA->id],
        ]);

        // Notification for Project B
        $notifB = NotificationModel::create([
            'user_id' => $this->member->id,
            'type' => TaskAssignedNotification::class,
            'title' => "Task Assigned: {$this->taskB->title}",
            'message' => "Project B task",
            'data' => ['type' => 'pm.task_assigned', 'project_id' => $this->projectB->id],
        ]);

        // Revoke access ONLY from Project A
        $deptService = app(DepartmentMembershipService::class);
        $deptService->removeMember($this->departmentA, $this->member);
        PmProjectMember::where('project_id', $this->projectA->id)->where('user_id', $this->member->id)->delete();
        $this->projectA->update(['owner_id' => $this->outsider->id, 'created_by' => $this->outsider->id]);

        $response = $this->actingAs($this->member)->getJson('/api/v1/notifications');
        $response->assertStatus(200);
        $data = $response->json('data');

        // Project A notification is hidden, but Project B notification remains visible
        $this->assertCount(1, $data);
        $this->assertEquals("Task Assigned: {$this->taskB->title}", $data[0]['title']);

        $unreadRes = $this->actingAs($this->member)->getJson('/api/v1/notifications/unread');
        $this->assertEquals(1, $unreadRes->json('unread_count'));
    }

    #[Test]
    public function read_all_only_marks_accessible_notifications_as_read(): void
    {
        // Notification for Project A (will be revoked)
        $notifA = NotificationModel::create([
            'user_id' => $this->member->id,
            'type' => TaskAssignedNotification::class,
            'title' => "Task Assigned: {$this->taskA->title}",
            'message' => "Project A task",
            'data' => ['type' => 'pm.task_assigned', 'project_id' => $this->projectA->id],
            'read_at' => null,
        ]);

        // Non-PMS notification
        $nonPm = NotificationModel::create([
            'user_id' => $this->member->id,
            'type' => 'App\\Notifications\\GenericAnnouncement',
            'title' => 'General MSA Announcement',
            'message' => 'General update',
            'data' => ['type' => 'general.announcement'],
            'read_at' => null,
        ]);

        // Revoke access to Project A
        $deptService = app(DepartmentMembershipService::class);
        $deptService->removeMember($this->departmentA, $this->member);
        PmProjectMember::where('project_id', $this->projectA->id)->where('user_id', $this->member->id)->delete();
        $this->projectA->update(['owner_id' => $this->outsider->id, 'created_by' => $this->outsider->id]);

        // Call read-all
        $res = $this->actingAs($this->member)->postJson('/api/v1/notifications/read-all');
        $res->assertStatus(200);

        // Non-PMS notification was marked read
        $this->assertNotNull($nonPm->fresh()->read_at);

        // Inaccessible Project A notification remains untouched (not read_at)
        $this->assertNull($notifA->fresh()->read_at);
    }
}

