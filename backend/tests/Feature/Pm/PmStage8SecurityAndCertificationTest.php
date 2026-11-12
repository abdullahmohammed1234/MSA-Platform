<?php

namespace Tests\Feature\Pm;

use App\Models\Department;
use App\Models\Notification as NotificationModel;
use App\Models\Role;
use App\Models\User;
use App\Pm\Enums\DepartmentRole;
use App\Pm\Enums\MilestoneStatus;
use App\Pm\Enums\ProjectPriority;
use App\Pm\Enums\ProjectRole;
use App\Pm\Enums\ProjectStatus;
use App\Pm\Enums\ProjectVisibility;
use App\Pm\Enums\TaskStatus;
use App\Pm\Models\PmDepartmentMembership;
use App\Pm\Models\PmMilestone;
use App\Pm\Models\PmProject;
use App\Pm\Models\PmProjectMember;
use App\Pm\Models\PmTask;
use App\Pm\Services\DepartmentMembershipService;
use App\Pm\Services\PmNotificationService;
use App\Services\ApplicationAccessService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PmStage8SecurityAndCertificationTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $executive;
    protected User $deptLead;
    protected User $collaborator;
    protected User $outsider;

    protected Department $dawahDept;
    protected Department $mediaDept;
    protected PmProject $privateProject;
    protected PmTask $confidentialTask;

    protected function setUp(): void
    {
        parent::setUp();

        $appAccess = app(ApplicationAccessService::class);
        $deptService = app(DepartmentMembershipService::class);

        // 1. Departments
        $this->dawahDept = Department::create(['name' => 'Dawah & Outreach', 'code' => 'DAW', 'slug' => 'dawah']);
        $this->mediaDept = Department::create(['name' => 'Media & Comms', 'code' => 'MED', 'slug' => 'media']);

        // 2. Roles & Users
        $superAdminRole = Role::firstOrCreate(['slug' => 'super-admin'], ['uuid' => (string) Str::uuid(), 'name' => 'Super Admin']);
        $execRole = Role::firstOrCreate(['slug' => 'executive'], ['uuid' => (string) Str::uuid(), 'name' => 'Executive']);

        $this->superAdmin = User::factory()->create(['name' => 'Super Admin User', 'email' => 'superadmin@sfu.ca']);
        $this->superAdmin->roles()->sync([$superAdminRole->id]);

        $this->executive = User::factory()->create(['name' => 'Executive VP', 'email' => 'exec@sfu.ca']);
        $this->executive->roles()->sync([$execRole->id]);

        $this->deptLead = User::factory()->create(['name' => 'Dawah Lead', 'email' => 'dawahlead@sfu.ca']);
        $this->collaborator = User::factory()->create(['name' => 'Media Collaborator', 'email' => 'mediacollab@sfu.ca']);
        $this->outsider = User::factory()->create(['name' => 'General Member', 'email' => 'general@sfu.ca']);

        // Grant PMS application access to all test personas
        foreach ([$this->superAdmin, $this->executive, $this->deptLead, $this->collaborator, $this->outsider] as $u) {
            $appAccess->grant($u, 'projects');
        }

        // Dept lead belongs to Dawah
        PmDepartmentMembership::create([
            'department_id' => $this->dawahDept->id,
            'user_id' => $this->deptLead->id,
            'department_role' => DepartmentRole::Lead,
            'status' => 'active',
            'start_date' => now()->toDateString(),
        ]);

        // Collaborator belongs to Media
        PmDepartmentMembership::create([
            'department_id' => $this->mediaDept->id,
            'user_id' => $this->collaborator->id,
            'department_role' => DepartmentRole::Member,
            'status' => 'active',
            'start_date' => now()->toDateString(),
        ]);

        // 3. Department-Private Project (Owned by Dawah, Participating: Media)
        $this->privateProject = PmProject::create([
            'name' => 'Confidential Outreach Strategy',
            'slug' => 'confidential-outreach-strategy',
            'code' => 'DAW-001',
            'visibility' => ProjectVisibility::DepartmentPrivate,
            'department_id' => $this->dawahDept->id,
            'owner_id' => $this->deptLead->id,
            'status' => ProjectStatus::InProgress,
            'priority' => ProjectPriority::High,
            'created_by' => $this->deptLead->id,
        ]);

        PmProjectMember::create([
            'project_id' => $this->privateProject->id,
            'user_id' => $this->deptLead->id,
            'project_role' => ProjectRole::Owner,
            'invitation_status' => 'accepted',
        ]);

        // Add Media as a participating department
        $this->privateProject->participatingDepartments()->attach($this->mediaDept->id);

        // 4. Confidential Task
        $this->confidentialTask = PmTask::create([
            'project_id' => $this->privateProject->id,
            'title' => 'Confidential Donor Pitch Deck',
            'status' => TaskStatus::InProgress,
            'priority' => ProjectPriority::Urgent,
            'estimated_hours' => 12.0,
            'assigned_to' => $this->deptLead->id,
            'created_by' => $this->deptLead->id,
            'due_date' => Carbon::now()->addDays(3),
        ]);
    }

    /**
     * Test Option B: Super-Admin and Executives WITHOUT department membership or
     * explicit project member invitation are strictly denied analytics access.
     */
    public function test_superadmin_and_executives_are_forbidden_from_viewing_private_project_analytics()
    {
        // 1. Super-admin receives 403
        $resSuper = $this->actingAs($this->superAdmin)
            ->getJson("/api/v1/pm/analytics/projects/{$this->privateProject->uuid}");
        $resSuper->assertStatus(403);

        // 2. Executive receives 403
        $resExec = $this->actingAs($this->executive)
            ->getJson("/api/v1/pm/analytics/projects/{$this->privateProject->uuid}");
        $resExec->assertStatus(403);

        // 3. Outsider receives 403
        $resOutsider = $this->actingAs($this->outsider)
            ->getJson("/api/v1/pm/analytics/projects/{$this->privateProject->uuid}");
        $resOutsider->assertStatus(403);
    }

    /**
     * Test Option B: Super-Admin and Executives cannot export private project reports.
     */
    public function test_superadmin_and_executives_are_forbidden_from_exporting_private_project_reports()
    {
        $resSuper = $this->actingAs($this->superAdmin)
            ->get("/api/v1/pm/projects/{$this->privateProject->uuid}/export?type=progress&format=csv");
        $resSuper->assertStatus(403);

        $resExec = $this->actingAs($this->executive)
            ->get("/api/v1/pm/projects/{$this->privateProject->uuid}/export?type=progress&format=pdf");
        $resExec->assertStatus(403);
    }

    /**
     * Test Option B: Department Lead and Participating Collaborator CAN access analytics & reports.
     */
    public function test_authorized_lead_and_participating_member_can_access_analytics_and_export()
    {
        // Dept Lead: 200 OK on analytics
        $resLeadAnalytics = $this->actingAs($this->deptLead)
            ->getJson("/api/v1/pm/analytics/projects/{$this->privateProject->uuid}");
        $resLeadAnalytics->assertStatus(200);
        $resLeadAnalytics->assertJsonPath('data.project.name', 'Confidential Outreach Strategy');

        // Dept Lead: 200 OK on export
        $resLeadExport = $this->actingAs($this->deptLead)
            ->get("/api/v1/pm/projects/{$this->privateProject->uuid}/export?type=progress&format=csv");
        $resLeadExport->assertStatus(200);

        // Participating Collaborator (Media Dept): 200 OK on analytics
        $resCollabAnalytics = $this->actingAs($this->collaborator)
            ->getJson("/api/v1/pm/analytics/projects/{$this->privateProject->uuid}");
        $resCollabAnalytics->assertStatus(200);

        // Participating Collaborator: 200 OK on export
        $resCollabExport = $this->actingAs($this->collaborator)
            ->get("/api/v1/pm/projects/{$this->privateProject->uuid}/export?type=progress&format=csv");
        $resCollabExport->assertStatus(200);
    }

    /**
     * Test Option B: Global Task Export does NOT leak private project tasks to unauthorized users.
     */
    public function test_global_task_export_never_leaks_confidential_tasks_to_unauthorized_users()
    {
        // Super-admin downloads global task export
        $resSuper = $this->actingAs($this->superAdmin)
            ->get('/api/v1/pm/tasks/export?format=csv');
        $resSuper->assertStatus(200);
        $contentSuper = $resSuper->streamedContent();
        $this->assertStringNotContainsString('Confidential Donor Pitch Deck', $contentSuper);

        // Outsider downloads global task export
        $resOutsider = $this->actingAs($this->outsider)
            ->get('/api/v1/pm/tasks/export?format=csv');
        $resOutsider->assertStatus(200);
        $contentOutsider = $resOutsider->streamedContent();
        $this->assertStringNotContainsString('Confidential Donor Pitch Deck', $contentOutsider);

        // Authorized Lead downloads global task export -> task IS present
        $resLead = $this->actingAs($this->deptLead)
            ->get('/api/v1/pm/tasks/export?format=csv');
        $resLead->assertStatus(200);
        $contentLead = $resLead->streamedContent();
        $this->assertStringContainsString('Confidential Donor Pitch Deck', $contentLead);
    }

    /**
     * Test Option B: Notification Service never notifies users who lack project access.
     */
    public function test_notification_service_revalidates_option_b_before_dispatching()
    {
        $notificationService = app(PmNotificationService::class);

        // Attempt to notify outsider of assignment to confidential task
        $notificationService->notifyTaskAssigned($this->confidentialTask, [$this->outsider->id], $this->deptLead);

        // Verify outsider received ZERO notifications
        $this->assertEquals(0, NotificationModel::where('user_id', $this->outsider->id)->count());

        // Attempt to notify superadmin (not a project member)
        $notificationService->notifyTaskAssigned($this->confidentialTask, [$this->superAdmin->id], $this->deptLead);
        $this->assertEquals(0, NotificationModel::where('user_id', $this->superAdmin->id)->count());

        // Notify dept lead (authorized) from another user
        $notificationService->notifyTaskAssigned($this->confidentialTask, [$this->deptLead->id], $this->collaborator);
        $this->assertEquals(1, NotificationModel::where('user_id', $this->deptLead->id)->count());
    }
}