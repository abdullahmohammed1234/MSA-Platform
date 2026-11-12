<?php

namespace Tests\Feature\Pm;

use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use App\Pm\Enums\DepartmentRole;
use App\Pm\Enums\DependencyType;
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
use App\Pm\Models\PmTaskDependency;
use App\Pm\Services\DepartmentMembershipService;
use App\Pm\Services\PmSchedulingService;
use App\Services\ApplicationAccessService;
use Database\Seeders\PmDepartmentSeeder;
use Database\Seeders\PmRolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PmSchedulingStage2ServiceAndApiTest extends TestCase
{
    use RefreshDatabase;

    protected PmSchedulingService $schedulingService;
    protected ApplicationAccessService $appAccessService;
    protected DepartmentMembershipService $deptService;

    protected User $managerUser;
    protected User $viewerUser;
    protected User $externalUser;
    protected Department $itDept;
    protected PmProject $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PmDepartmentSeeder::class);
        $this->seed(PmRolePermissionSeeder::class);

        $this->schedulingService = app(PmSchedulingService::class);
        $this->appAccessService = app(ApplicationAccessService::class);
        $this->deptService = app(DepartmentMembershipService::class);

        $this->itDept = Department::where('slug', 'it')->firstOrFail();
        $memberRole = Role::firstOrCreate(['slug' => 'member'], ['uuid' => (string) \Illuminate\Support\Str::uuid(), 'name' => 'Member']);

        // Manager user
        $this->managerUser = User::factory()->create([
            'name' => 'IT Project Manager',
            'email' => 'manager@sfumsa.ca',
            'is_active' => true,
        ]);
        $this->managerUser->roles()->sync([$memberRole->id]);
        $this->deptService->addMember($this->itDept, $this->managerUser, DepartmentRole::Member);
        $this->appAccessService->grant($this->managerUser, 'projects', $this->managerUser);

        // Viewer user (read only)
        $this->viewerUser = User::factory()->create([
            'name' => 'IT Viewer',
            'email' => 'viewer@sfumsa.ca',
            'is_active' => true,
        ]);
        $this->viewerUser->roles()->sync([$memberRole->id]);
        $this->deptService->addMember($this->itDept, $this->viewerUser, DepartmentRole::Member);
        $this->appAccessService->grant($this->viewerUser, 'projects', $this->viewerUser);

        // External user (no project access)
        $this->externalUser = User::factory()->create([
            'name' => 'External User',
            'email' => 'external@sfumsa.ca',
            'is_active' => true,
        ]);
        $this->externalUser->roles()->sync([$memberRole->id]);
        $this->appAccessService->grant($this->externalUser, 'projects', $this->externalUser);

        // Project
        $this->project = PmProject::create([
            'department_id' => $this->itDept->id,
            'name' => 'Core Infrastructure Upgrade',
            'visibility' => ProjectVisibility::DepartmentPrivate,
            'status' => ProjectStatus::InProgress,
            'priority' => ProjectPriority::High,
            'owner_id' => $this->managerUser->id,
        ]);

        PmProjectMember::create([
            'project_id' => $this->project->id,
            'user_id' => $this->managerUser->id,
            'project_role' => ProjectRole::Manager,
            'invitation_status' => 'accepted',
        ]);

        PmProjectMember::create([
            'project_id' => $this->project->id,
            'user_id' => $this->viewerUser->id,
            'project_role' => ProjectRole::Viewer,
            'invitation_status' => 'accepted',
        ]);
    }

    public function test_detects_finish_to_start_conflict(): void
    {
        $taskA = PmTask::create([
            'project_id' => $this->project->id,
            'title' => 'Database Schema Migration',
            'start_date' => '2026-11-01',
            'due_date' => '2026-11-10',
            'created_by' => $this->managerUser->id,
        ]);

        // Task B starts Nov 8 (BEFORE Task A finishes Nov 10) -> Conflict!
        $taskB = PmTask::create([
            'project_id' => $this->project->id,
            'title' => 'Data Seeding & Verification',
            'start_date' => '2026-11-08',
            'due_date' => '2026-11-15',
            'created_by' => $this->managerUser->id,
        ]);

        PmTaskDependency::create([
            'project_id' => $this->project->id,
            'task_id' => $taskB->id,
            'depends_on_task_id' => $taskA->id,
            'dependency_type' => DependencyType::FinishToStart,
            'lag_days' => 0,
            'created_by' => $this->managerUser->id,
        ]);

        $analysis = $this->schedulingService->detectConflicts($taskB);

        $this->assertTrue($analysis['has_conflicts']);
        $this->assertCount(1, $analysis['conflicts']);
        $this->assertEquals('finish_to_start_violation', $analysis['conflicts'][0]['conflict_type']);
        $this->assertEquals('2026-11-11', $analysis['conflicts'][0]['required_date']);
        $this->assertEquals('2026-11-08', $analysis['conflicts'][0]['actual_date']);
    }

    public function test_finish_to_start_with_positive_lag(): void
    {
        $taskA = PmTask::create([
            'project_id' => $this->project->id,
            'title' => 'API Deployment',
            'start_date' => '2026-11-01',
            'due_date' => '2026-11-05',
            'created_by' => $this->managerUser->id,
        ]);

        // Task B needs 2 days lag after finish.
        // Required start = Nov 5 + 2 lag + 1 = Nov 8.
        $taskB = PmTask::create([
            'project_id' => $this->project->id,
            'title' => 'Load Testing',
            'start_date' => '2026-11-07', // Too early!
            'due_date' => '2026-11-12',
            'created_by' => $this->managerUser->id,
        ]);

        PmTaskDependency::create([
            'project_id' => $this->project->id,
            'task_id' => $taskB->id,
            'depends_on_task_id' => $taskA->id,
            'dependency_type' => DependencyType::FinishToStart,
            'lag_days' => 2,
            'created_by' => $this->managerUser->id,
        ]);

        $analysis = $this->schedulingService->detectConflicts($taskB);
        $this->assertTrue($analysis['has_conflicts']);
        $this->assertEquals('2026-11-08', $analysis['conflicts'][0]['required_date']);

        // Reschedule task B to Nov 8 -> Conflict disappears
        $taskB->update(['start_date' => '2026-11-08']);
        $analysisValid = $this->schedulingService->detectConflicts($taskB);
        $this->assertFalse($analysisValid['has_conflicts']);
    }

    public function test_detects_start_to_start_and_finish_to_finish_conflicts(): void
    {
        $taskA = PmTask::create([
            'project_id' => $this->project->id,
            'title' => 'Sprint Design',
            'start_date' => '2026-11-05',
            'due_date' => '2026-11-15',
            'created_by' => $this->managerUser->id,
        ]);

        // Start-to-Start: Task B cannot start before Task A starts (Nov 5)
        $taskSS = PmTask::create([
            'project_id' => $this->project->id,
            'title' => 'Sprint Copywriting',
            'start_date' => '2026-11-03', // Earlier than Nov 5 -> conflict
            'due_date' => '2026-11-10',
            'created_by' => $this->managerUser->id,
        ]);

        PmTaskDependency::create([
            'project_id' => $this->project->id,
            'task_id' => $taskSS->id,
            'depends_on_task_id' => $taskA->id,
            'dependency_type' => DependencyType::StartToStart,
            'lag_days' => 0,
            'created_by' => $this->managerUser->id,
        ]);

        $ssAnalysis = $this->schedulingService->detectConflicts($taskSS);
        $this->assertTrue($ssAnalysis['has_conflicts']);
        $this->assertEquals('start_to_start_violation', $ssAnalysis['conflicts'][0]['conflict_type']);

        // Finish-to-Finish: Task C cannot finish before Task A finishes (Nov 15)
        $taskFF = PmTask::create([
            'project_id' => $this->project->id,
            'title' => 'Design Signoff',
            'start_date' => '2026-11-08',
            'due_date' => '2026-11-12', // Finishes before Nov 15 -> conflict
            'created_by' => $this->managerUser->id,
        ]);

        PmTaskDependency::create([
            'project_id' => $this->project->id,
            'task_id' => $taskFF->id,
            'depends_on_task_id' => $taskA->id,
            'dependency_type' => DependencyType::FinishToFinish,
            'lag_days' => 0,
            'created_by' => $this->managerUser->id,
        ]);

        $ffAnalysis = $this->schedulingService->detectConflicts($taskFF);
        $this->assertTrue($ffAnalysis['has_conflicts']);
        $this->assertEquals('finish_to_finish_violation', $ffAnalysis['conflicts'][0]['conflict_type']);
    }

    public function test_handles_missing_dates_safely_as_unresolved(): void
    {
        $taskA = PmTask::create([
            'project_id' => $this->project->id,
            'title' => 'Unscheduled Task A',
            'start_date' => null,
            'due_date' => null,
            'created_by' => $this->managerUser->id,
        ]);

        $taskB = PmTask::create([
            'project_id' => $this->project->id,
            'title' => 'Task B',
            'start_date' => '2026-11-10',
            'due_date' => '2026-11-15',
            'created_by' => $this->managerUser->id,
        ]);

        PmTaskDependency::create([
            'project_id' => $this->project->id,
            'task_id' => $taskB->id,
            'depends_on_task_id' => $taskA->id,
            'dependency_type' => DependencyType::FinishToStart,
            'created_by' => $this->managerUser->id,
        ]);

        $analysis = $this->schedulingService->detectConflicts($taskB);
        $this->assertFalse($analysis['has_conflicts']);
        $this->assertTrue($analysis['has_unresolved']);
        $this->assertCount(1, $analysis['unresolved']);
        $this->assertStringContainsString('Missing predecessor due date', $analysis['unresolved'][0]['reason']);
    }

    public function test_cascade_preview_preserves_durations_across_dependency_chain(): void
    {
        // Chain: A -> B -> C
        $taskA = PmTask::create([
            'project_id' => $this->project->id,
            'title' => 'Component A',
            'start_date' => '2026-11-01',
            'due_date' => '2026-11-05', // 5 days
            'created_by' => $this->managerUser->id,
        ]);

        $taskB = PmTask::create([
            'project_id' => $this->project->id,
            'title' => 'Component B',
            'start_date' => '2026-11-06',
            'due_date' => '2026-11-08', // 3 days (Nov 6, 7, 8)
            'created_by' => $this->managerUser->id,
        ]);

        $taskC = PmTask::create([
            'project_id' => $this->project->id,
            'title' => 'Component C',
            'start_date' => '2026-11-09',
            'due_date' => '2026-11-12', // 4 days (Nov 9, 10, 11, 12)
            'created_by' => $this->managerUser->id,
        ]);

        PmTaskDependency::create([
            'project_id' => $this->project->id,
            'task_id' => $taskB->id,
            'depends_on_task_id' => $taskA->id,
            'dependency_type' => DependencyType::FinishToStart,
            'created_by' => $this->managerUser->id,
        ]);

        PmTaskDependency::create([
            'project_id' => $this->project->id,
            'task_id' => $taskC->id,
            'depends_on_task_id' => $taskB->id,
            'dependency_type' => DependencyType::FinishToStart,
            'created_by' => $this->managerUser->id,
        ]);

        // Shift Task A forward by 5 days: Nov 1-5 -> Nov 6-10
        $preview = $this->schedulingService->previewCascade($taskA, '2026-11-06', '2026-11-10');

        $this->assertEquals(2, $preview['total_affected_count']);
        $this->assertEquals(3, $preview['max_depth_reached']);

        $affectedB = collect($preview['affected_tasks'])->firstWhere('id', $taskB->id);
        $this->assertNotNull($affectedB);
        // B must start Nov 11 and retain 3-day duration -> finishes Nov 13
        $this->assertEquals('2026-11-11', $affectedB['proposed_start_date']);
        $this->assertEquals('2026-11-13', $affectedB['proposed_due_date']);

        $affectedC = collect($preview['affected_tasks'])->firstWhere('id', $taskC->id);
        $this->assertNotNull($affectedC);
        // C must start Nov 14 and retain 4-day duration -> finishes Nov 17
        $this->assertEquals('2026-11-14', $affectedC['proposed_start_date']);
        $this->assertEquals('2026-11-17', $affectedC['proposed_due_date']);
    }

    public function test_transactional_reschedule_execution_with_cascade_and_audit_logging(): void
    {
        $taskA = PmTask::create([
            'project_id' => $this->project->id,
            'title' => 'Wireframes',
            'start_date' => '2026-11-01',
            'due_date' => '2026-11-04',
            'created_by' => $this->managerUser->id,
        ]);

        $taskB = PmTask::create([
            'project_id' => $this->project->id,
            'title' => 'Frontend Mockup',
            'start_date' => '2026-11-05',
            'due_date' => '2026-11-08',
            'created_by' => $this->managerUser->id,
        ]);

        PmTaskDependency::create([
            'project_id' => $this->project->id,
            'task_id' => $taskB->id,
            'depends_on_task_id' => $taskA->id,
            'created_by' => $this->managerUser->id,
        ]);

        // Reschedule Task A to Nov 5-9 with cascade=true
        $result = $this->schedulingService->executeReschedule(
            task: $taskA,
            newStartDate: '2026-11-05',
            newDueDate: '2026-11-09',
            cascade: true,
            actor: $this->managerUser
        );

        $this->assertEquals(2, $result['affected_count']);

        $taskA->refresh();
        $this->assertEquals('2026-11-05', $taskA->start_date->toDateString());
        $this->assertEquals('2026-11-09', $taskA->due_date->toDateString());

        $taskB->refresh();
        $this->assertEquals('2026-11-10', $taskB->start_date->toDateString());
        $this->assertEquals('2026-11-13', $taskB->due_date->toDateString());

        // Verify audit log entries
        $this->assertDatabaseHas('pm_activity_logs', [
            'project_id' => $this->project->id,
            'action' => 'task_rescheduled',
            'entity_id' => $taskA->id,
        ]);

        $this->assertDatabaseHas('pm_activity_logs', [
            'project_id' => $this->project->id,
            'action' => 'task_auto_rescheduled',
            'entity_id' => $taskB->id,
        ]);
    }

    public function test_milestone_slippage_detection(): void
    {
        $milestone = PmMilestone::create([
            'project_id' => $this->project->id,
            'title' => 'Phase 1 MVP Release',
            'due_date' => '2026-11-20',
            'status' => MilestoneStatus::Pending,
            'created_by' => $this->managerUser->id,
        ]);

        // Task within milestone target
        $task1 = PmTask::create([
            'project_id' => $this->project->id,
            'milestone_id' => $milestone->id,
            'title' => 'MVP Feature A',
            'start_date' => '2026-11-01',
            'due_date' => '2026-11-15',
            'created_by' => $this->managerUser->id,
        ]);

        $eval1 = $this->schedulingService->evaluateMilestoneCondition($milestone);
        $this->assertEquals('on_track', $eval1['condition']);

        // Task slipping past milestone target
        $task2 = PmTask::create([
            'project_id' => $this->project->id,
            'milestone_id' => $milestone->id,
            'title' => 'Delayed Feature B',
            'start_date' => '2026-11-10',
            'due_date' => '2026-11-25', // 5 days past milestone due date
            'created_by' => $this->managerUser->id,
        ]);

        $eval2 = $this->schedulingService->evaluateMilestoneCondition($milestone);
        $this->assertEquals('delayed', $eval2['condition']);
        $this->assertEquals(1, $eval2['exceeding_tasks_count']);
        $this->assertEquals('2026-11-25', $eval2['max_task_due_date']);
    }

    public function test_scheduling_api_endpoints_and_authorization(): void
    {
        $task = PmTask::create([
            'project_id' => $this->project->id,
            'title' => 'API Task',
            'start_date' => '2026-11-01',
            'due_date' => '2026-11-05',
            'created_by' => $this->managerUser->id,
        ]);

        // 1. Manager can get scheduling info
        Sanctum::actingAs($this->managerUser);
        $schedRes = $this->getJson("/api/v1/pm/tasks/{$task->uuid}/scheduling");
        $schedRes->assertStatus(200);
        $schedRes->assertJsonStructure(['task', 'scheduling']);

        // 2. Manager can preview reschedule
        $previewRes = $this->postJson("/api/v1/pm/tasks/{$task->uuid}/reschedule/preview", [
            'start_date' => '2026-11-05',
            'due_date' => '2026-11-10',
        ]);
        $previewRes->assertStatus(200);
        $previewRes->assertJsonFragment(['proposed_start_date' => '2026-11-05']);

        // 3. Manager can execute reschedule
        $execRes = $this->postJson("/api/v1/pm/tasks/{$task->uuid}/reschedule", [
            'start_date' => '2026-11-05',
            'due_date' => '2026-11-10',
            'cascade' => false,
        ]);
        $execRes->assertStatus(200);
        $execRes->assertJsonFragment(['start_date' => '2026-11-05', 'due_date' => '2026-11-10']);

        // 4. Viewer cannot reschedule (Option B write protection)
        Sanctum::actingAs($this->viewerUser);
        $viewerRes = $this->postJson("/api/v1/pm/tasks/{$task->uuid}/reschedule", [
            'start_date' => '2026-11-06',
            'due_date' => '2026-11-11',
        ]);
        $viewerRes->assertStatus(403);

        // 5. External user cannot view scheduling info
        Sanctum::actingAs($this->externalUser);
        $externalRes = $this->getJson("/api/v1/pm/tasks/{$task->uuid}/scheduling");
        $externalRes->assertStatus(403);
    }
}

