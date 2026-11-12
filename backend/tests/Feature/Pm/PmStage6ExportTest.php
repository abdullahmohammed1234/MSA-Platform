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
use App\Pm\Models\PmMilestone;
use App\Pm\Models\PmProject;
use App\Pm\Models\PmProjectMember;
use App\Pm\Models\PmTask;
use App\Services\ApplicationAccessService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PmStage6ExportTest extends TestCase
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

        $this->user = User::factory()->create(['name' => 'Report Manager', 'email' => 'manager@sfu.ca']);
        $this->outsider = User::factory()->create(['name' => 'Unauthorized User', 'email' => 'outsider@sfu.ca']);

        $appAccess->grant($this->user, 'projects');
        $appAccess->grant($this->outsider, 'projects');

        $this->dept = Department::create([
            'name' => 'Operations Department',
            'code' => 'OPS',
        ]);

        $this->project = PmProject::create([
            'name' => 'Operations Master Plan',
            'slug' => 'operations-master-plan',
            'code' => 'OPS-01',
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

        // Create tasks
        PmTask::create([
            'project_id' => $this->project->id,
            'title' => 'Venue Setup & Logistics',
            'status' => TaskStatus::Completed,
            'priority' => ProjectPriority::High,
            'estimated_hours' => 8.0,
            'assigned_to' => $this->user->id,
            'created_by' => $this->user->id,
            'completed_at' => Carbon::now()->subDay(),
        ]);

        PmTask::create([
            'project_id' => $this->project->id,
            'title' => 'Volunteer Briefing',
            'status' => TaskStatus::InProgress,
            'priority' => ProjectPriority::Medium,
            'estimated_hours' => 4.0,
            'assigned_to' => $this->user->id,
            'created_by' => $this->user->id,
            'due_date' => Carbon::now()->addDays(2),
        ]);

        // Create milestone
        PmMilestone::create([
            'project_id' => $this->project->id,
            'title' => 'Pre-Event Execution Phase',
            'status' => MilestoneStatus::Pending,
            'due_date' => Carbon::now()->addDays(5),
            'order' => 1,
        ]);
    }

    public function test_export_project_progress_csv()
    {
        $response = $this->actingAs($this->user)
            ->get("/api/v1/pm/projects/{$this->project->uuid}/export?type=progress&format=csv");

        $response->assertStatus(200);
        $this->assertTrue(str_contains($response->headers->get('Content-Type'), 'text/csv'));
        // dump($response->headers->get('Content-Disposition'));
        $this->assertNotEmpty($response->headers->get('Content-Disposition'));
        $this->assertTrue(str_contains(strtolower($response->headers->get('Content-Disposition')), 'progress'));

        $content = $response->streamedContent();
        $this->assertStringContainsString('Operations Master Plan', $content);
        $this->assertStringContainsString('Venue Setup & Logistics', $content);
        $this->assertStringContainsString('Completion Rate', $content);
    }

    public function test_export_project_progress_xlsx()
    {
        $response = $this->actingAs($this->user)
            ->get("/api/v1/pm/projects/{$this->project->uuid}/export?type=progress&format=xlsx");

        $response->assertStatus(200);
        $this->assertTrue(str_contains($response->headers->get('Content-Type'), 'spreadsheetml.sheet'));
        $this->assertTrue(str_contains($response->headers->get('Content-Disposition'), '.xlsx'));
        
        $content = $response->streamedContent();
        $this->assertNotEmpty($content);
    }

    public function test_export_project_progress_pdf()
    {
        $response = $this->actingAs($this->user)
            ->get("/api/v1/pm/projects/{$this->project->uuid}/export?type=progress&format=pdf");

        $response->assertStatus(200);
        $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));
        $this->assertTrue(str_contains($response->headers->get('Content-Disposition'), '.pdf'));

        $content = $response->streamedContent();
        $this->assertStringStartsWith('%PDF-', $content);
    }

    public function test_export_project_workload_csv()
    {
        $response = $this->actingAs($this->user)
            ->get("/api/v1/pm/projects/{$this->project->uuid}/export?type=workload&format=csv");

        $response->assertStatus(200);
        $this->assertTrue(str_contains($response->headers->get('Content-Type'), 'text/csv'));
        $content = $response->streamedContent();
        $this->assertStringContainsString('Workload Allocation', $content);
        $this->assertStringContainsString('Weekly Capacity', $content);
        $this->assertStringContainsString($this->user->email, $content);
    }

    public function test_export_tasks_csv()
    {
        $response = $this->actingAs($this->user)
            ->get('/api/v1/pm/tasks/export?format=csv');

        $response->assertStatus(200);
        $this->assertTrue(str_contains($response->headers->get('Content-Type'), 'text/csv'));
        $content = $response->streamedContent();
        $this->assertStringContainsString('Venue Setup & Logistics', $content);
    }

    public function test_unauthorized_user_cannot_export_private_project_under_option_b()
    {
        $response = $this->actingAs($this->outsider)
            ->get("/api/v1/pm/projects/{$this->project->uuid}/export?type=progress&format=csv");

        $response->assertStatus(403);
    }
}