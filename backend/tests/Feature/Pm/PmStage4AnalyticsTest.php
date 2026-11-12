<?php

namespace Tests\Feature\Pm;

use App\Models\Department;
use App\Models\User;
use App\Pm\Enums\MilestoneStatus;
use App\Pm\Enums\ProjectPriority;
use App\Pm\Enums\ProjectRole;
use App\Pm\Enums\ProjectStatus;
use App\Pm\Enums\ProjectVisibility;
use App\Pm\Enums\TaskStatus;
use App\Pm\Models\PmActivityLog;
use App\Pm\Models\PmMilestone;
use App\Pm\Models\PmProject;
use App\Pm\Models\PmProjectMember;
use App\Pm\Models\PmTask;
use App\Services\ApplicationAccessService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PmStage4AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected User $outsider;
    protected Department $dept;
    protected PmProject $project;

    protected function setUp(): void
    {
        parent::setUp();

        $appAccess = app(ApplicationAccessService::class);

        $this->user = User::factory()->create(['name' => 'Analytics Lead', 'email' => 'lead@sfu.ca']);
        $this->outsider = User::factory()->create(['name' => 'Outsider User', 'email' => 'outsider@sfu.ca']);

        $appAccess->grant($this->user, 'projects');
        $appAccess->grant($this->outsider, 'projects');

        $this->dept = Department::create([
            'name' => 'Analytics Department',
            'code' => 'ANL',
        ]);

        $this->project = PmProject::create([
            'name' => 'Analytics Test Project',
            'slug' => 'analytics-test-project',
            'visibility' => ProjectVisibility::DepartmentPrivate,
            'department_id' => $this->dept->id,
            'owner_id' => $this->user->id,
            'status' => ProjectStatus::InProgress,
            'priority' => ProjectPriority::High,
            'created_by' => $this->user->id,
        ]);

        PmProjectMember::create([
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'project_role' => ProjectRole::Owner,
            'invitation_status' => 'accepted',
        ]);
    }

    public function test_project_analytics_calculates_accurate_kpis_and_metrics()
    {
        // Create 2 completed tasks, 1 in-progress task, 1 overdue task
        $t1 = PmTask::create([
            'project_id' => $this->project->id,
            'title' => 'Completed Task 1',
            'status' => TaskStatus::Completed,
            'priority' => ProjectPriority::Medium,
            'estimated_hours' => 10.0,
            'effort_source' => 'manual',
            'created_at' => Carbon::now()->subDays(5),
            'completed_at' => Carbon::now()->subDays(2),
            'assigned_to' => $this->user->id,
            'created_by' => $this->user->id,
        ]);

        $t2 = PmTask::create([
            'project_id' => $this->project->id,
            'title' => 'Completed Task 2',
            'status' => TaskStatus::Completed,
            'priority' => ProjectPriority::High,
            'estimated_hours' => 5.0,
            'effort_source' => 'manual',
            'created_at' => Carbon::now()->subDays(4),
            'completed_at' => Carbon::now()->subDays(1),
            'assigned_to' => $this->user->id,
            'created_by' => $this->user->id,
        ]);

        $t3 = PmTask::create([
            'project_id' => $this->project->id,
            'title' => 'In Progress Task',
            'status' => TaskStatus::InProgress,
            'priority' => ProjectPriority::Medium,
            'estimated_hours' => 8.0,
            'effort_source' => 'manual',
            'assigned_to' => $this->user->id,
            'created_by' => $this->user->id,
        ]);

        $t4 = PmTask::create([
            'project_id' => $this->project->id,
            'title' => 'Overdue Task',
            'status' => TaskStatus::Todo,
            'priority' => ProjectPriority::Urgent,
            'due_date' => Carbon::now()->subDays(3),
            'estimated_hours' => 4.0,
            'effort_source' => 'manual',
            'assigned_to' => $this->user->id,
            'created_by' => $this->user->id,
        ]);

        // Create 1 completed on-time milestone, 1 overdue milestone
        PmMilestone::create([
            'project_id' => $this->project->id,
            'title' => 'On Time Milestone',
            'status' => MilestoneStatus::Reached,
            'due_date' => Carbon::now()->addDays(5),
            'completed_at' => Carbon::now()->subDays(1),
            'created_by' => $this->user->id,
        ]);

        PmMilestone::create([
            'project_id' => $this->project->id,
            'title' => 'Overdue Milestone',
            'status' => MilestoneStatus::Pending,
            'due_date' => Carbon::now()->subDays(2),
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/pm/analytics/projects/{$this->project->uuid}");

        $response->assertStatus(200);
        $data = $response->json('data');

        // Task Counts
        $this->assertEquals(4, $data['task_counts']['total']);
        $this->assertEquals(2, $data['task_counts']['completed']);
        $this->assertEquals(1, $data['task_counts']['in_progress']);
        $this->assertEquals(1, $data['task_counts']['todo']);
        $this->assertEquals(1, $data['task_counts']['overdue']);
        $this->assertEquals(50.0, $data['project']['completion_rate']);

        // Effort Hours
        $this->assertEquals(27.0, $data['effort_hours']['total']);
        $this->assertEquals(15.0, $data['effort_hours']['completed']);
        $this->assertEquals(12.0, $data['effort_hours']['remaining']);

        // Milestones
        $this->assertEquals(2, $data['milestones']['total']);
        $this->assertEquals(1, $data['milestones']['completed']);
        $this->assertEquals(1, $data['milestones']['pending']);
        $this->assertEquals(1, $data['milestones']['overdue']);
        $this->assertEquals(100.0, $data['milestones']['on_time_rate']);

        // Lead time calculated (average of 3 days and 3 days = 3.0 days)
        $this->assertNotNull($data['cycle_time_metrics']['avg_lead_time_days']);

        // Velocity trend contains 8 weeks
        $this->assertCount(8, $data['velocity_trend']);
    }

    public function test_option_b_forbids_unauthorized_user_from_accessing_project_analytics()
    {
        $response = $this->actingAs($this->outsider)
            ->getJson("/api/v1/pm/analytics/projects/{$this->project->uuid}");

        $response->assertStatus(403);
    }

    public function test_department_analytics_excludes_inaccessible_projects()
    {
        // User is member of $this->project in $this->dept
        // Create another department-private project in same department without membership for user
        $privateProject = PmProject::create([
            'name' => 'Restricted Peer Project',
            'slug' => 'restricted-peer-project',
            'visibility' => ProjectVisibility::DepartmentPrivate,
            'department_id' => $this->dept->id,
            'owner_id' => $this->outsider->id,
            'status' => ProjectStatus::InProgress,
            'priority' => ProjectPriority::Medium,
            'created_by' => $this->outsider->id,
        ]);

        PmProjectMember::create([
            'project_id' => $privateProject->id,
            'user_id' => $this->outsider->id,
            'project_role' => ProjectRole::Owner,
            'invitation_status' => 'accepted',
        ]);

        PmTask::create([
            'project_id' => $privateProject->id,
            'title' => 'Secret Department Task',
            'status' => TaskStatus::Completed,
            'assigned_to' => $this->outsider->id,
            'created_by' => $this->outsider->id,
        ]);

        // Outsider queries department analytics (has access to Restricted Peer Project, but NOT to $this->project)
        $response = $this->actingAs($this->outsider)
            ->getJson("/api/v1/pm/analytics/departments/{$this->dept->id}");

        $response->assertStatus(200);
        $data = $response->json('data');

        // Outsider must only see the 1 project they are member of
        $this->assertEquals(1, $data['accessible_projects_count']);
        $projectNames = collect($data['projects'])->pluck('name')->all();
        $this->assertContains('Restricted Peer Project', $projectNames);
        $this->assertNotContains('Analytics Test Project', $projectNames);
    }

    public function test_dashboard_analytics_aggregates_user_scope()
    {
        PmTask::create([
            'project_id' => $this->project->id,
            'title' => 'User Active Task',
            'status' => TaskStatus::InProgress,
            'assigned_to' => $this->user->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/pm/analytics/dashboard');

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertGreaterThanOrEqual(1, $data['accessible_projects_count']);
        $this->assertGreaterThanOrEqual(1, $data['my_open_tasks']);
        $this->assertCount(8, $data['velocity_trend']);
    }
}

