<?php

namespace Tests\Feature\Pm;

use App\Models\Department;
use App\Models\User;
use App\Pm\Enums\ProjectPriority;
use App\Pm\Enums\ProjectStatus;
use App\Pm\Enums\ProjectVisibility;
use App\Pm\Enums\TaskStatus;
use App\Pm\Models\PmProject;
use App\Pm\Models\PmTask;
use App\Pm\Models\PmUserCapacity;
use Database\Seeders\PmDepartmentSeeder;
use Database\Seeders\PmRolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class PmPhase13Stage1SchemaAndModelsTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected PmProject $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PmDepartmentSeeder::class);
        $this->seed(PmRolePermissionSeeder::class);

        $this->user = User::factory()->create([
            'email' => 'stage1.tester@sfumsa.ca',
            'is_active' => true,
        ]);

        $itDept = Department::where('slug', 'it')->firstOrFail();

        $this->project = PmProject::create([
            'uuid' => (string) Str::uuid(),
            'department_id' => $itDept->id,
            'owner_id' => $this->user->id,
            'name' => 'Stage 1 Test Project',
            'visibility' => ProjectVisibility::DepartmentPrivate,
            'status' => ProjectStatus::InProgress,
            'priority' => ProjectPriority::Medium,
        ]);
    }

    public function test_tasks_table_has_effort_and_source_fields(): void
    {
        $this->assertTrue(Schema::hasColumn('pm_tasks', 'estimated_hours'));
        $this->assertTrue(Schema::hasColumn('pm_tasks', 'effort_source'));
    }

    public function test_manual_effort_estimate_is_persisted_and_preserved_on_date_shift(): void
    {
        $task = PmTask::create([
            'uuid' => (string) Str::uuid(),
            'project_id' => $this->project->id,
            'title' => 'Manual Estimate Deliverable',
            'status' => TaskStatus::Todo,
            'priority' => ProjectPriority::High,
            'start_date' => '2026-11-02', // Monday
            'due_date' => '2026-11-06',   // Friday (5 working days)
            'estimated_hours' => 15.50,
            'effort_source' => 'manual',
        ]);

        $this->assertEquals(15.50, (float) $task->estimated_hours);
        $this->assertEquals('manual', $task->effort_source);
        $this->assertEquals(15.50, $task->effective_estimated_hours);

        // Shift dates by 2 weeks
        $task->update([
            'start_date' => '2026-11-16',
            'due_date' => '2026-11-20',
        ]);

        $task->refresh();
        // Invariant: manual estimates must never be overwritten on date shifts
        $this->assertEquals(15.50, (float) $task->estimated_hours);
        $this->assertEquals('manual', $task->effort_source);
    }

    public function test_inferred_effort_calculates_working_days_correctly(): void
    {
        // 5 working days (Monday to Friday) @ 2.0 hrs/day = 10.0 hrs
        $task = PmTask::create([
            'uuid' => (string) Str::uuid(),
            'project_id' => $this->project->id,
            'title' => 'Inferred Estimate Deliverable',
            'status' => TaskStatus::Todo,
            'priority' => ProjectPriority::Medium,
            'start_date' => '2026-11-02', // Monday
            'due_date' => '2026-11-06',   // Friday
            'estimated_hours' => null,
            'effort_source' => 'inferred',
        ]);

        $this->assertEquals(10.0, $task->effective_estimated_hours);
        $this->assertEquals(10.0, $task->calculateInferredEffort());

        // Zero-working-day fallback (Saturday to Sunday only) -> defaults to 1 working day equivalent (2.0 hrs)
        $weekendTask = PmTask::create([
            'uuid' => (string) Str::uuid(),
            'project_id' => $this->project->id,
            'title' => 'Weekend Staging Task',
            'status' => TaskStatus::Todo,
            'priority' => ProjectPriority::Low,
            'start_date' => '2026-11-07', // Saturday
            'due_date' => '2026-11-08',   // Sunday
        ]);

        $this->assertEquals(2.0, $weekendTask->effective_estimated_hours);

        // Null dates return 0.0 hrs
        $unscheduledTask = PmTask::create([
            'uuid' => (string) Str::uuid(),
            'project_id' => $this->project->id,
            'title' => 'Unscheduled Backlog Item',
            'status' => TaskStatus::Todo,
            'priority' => ProjectPriority::Low,
            'start_date' => null,
            'due_date' => null,
        ]);

        $this->assertEquals(0.0, $unscheduledTask->effective_estimated_hours);
    }

    public function test_user_capacities_table_schema_and_relations(): void
    {
        $this->assertTrue(Schema::hasTable('pm_user_capacities'));
        $this->assertTrue(Schema::hasColumn('pm_user_capacities', 'weekly_capacity_hours'));
        $this->assertTrue(Schema::hasColumn('pm_user_capacities', 'daily_capacity_hours'));
        $this->assertTrue(Schema::hasColumn('pm_user_capacities', 'daily_availability'));
        $this->assertTrue(Schema::hasColumn('pm_user_capacities', 'max_concurrent_tasks'));
        $this->assertTrue(Schema::hasColumn('pm_user_capacities', 'effective_from'));
        $this->assertTrue(Schema::hasColumn('pm_user_capacities', 'effective_to'));

        $capacity = PmUserCapacity::create([
            'user_id' => $this->user->id,
            'weekly_capacity_hours' => 12.00,
            'daily_capacity_hours' => 2.40,
            'daily_availability' => [
                'mon' => 3.0,
                'tue' => 2.0,
                'wed' => 3.0,
                'thu' => 2.0,
                'fri' => 2.0,
                'sat' => 0.0,
                'sun' => 0.0,
            ],
            'max_concurrent_tasks' => 4,
            'effective_from' => '2026-11-01',
            'effective_to' => '2026-12-31',
            'notes' => 'Fall Term Volunteer Schedule',
        ]);

        $this->assertEquals(12.00, (float) $capacity->weekly_capacity_hours);
        $this->assertEquals(2.40, (float) $capacity->daily_capacity_hours);
        $this->assertEquals(4, $capacity->max_concurrent_tasks);
        $this->assertEquals(3.0, $capacity->getDailyCapacityForDay(1)); // Monday
        $this->assertEquals(2.0, $capacity->getDailyCapacityForDay(2)); // Tuesday
        $this->assertEquals(0.0, $capacity->getDailyCapacityForDay(6)); // Saturday

        // Test effective date evaluation
        $this->assertTrue($capacity->isEffectiveOn('2026-11-15'));
        $this->assertFalse($capacity->isEffectiveOn('2026-10-31'));
        $this->assertFalse($capacity->isEffectiveOn('2027-01-01'));

        // Test User model relations
        $this->assertInstanceOf(PmUserCapacity::class, $this->user->pmCapacity);
        $this->assertEquals($capacity->id, $this->user->pmCapacity->id);
    }

    public function test_user_capacity_cascade_deletes_with_user(): void
    {
        $targetUser = User::factory()->create();
        PmUserCapacity::create([
            'user_id' => $targetUser->id,
            'weekly_capacity_hours' => 10.00,
        ]);

        $this->assertDatabaseHas('pm_user_capacities', ['user_id' => $targetUser->id]);

        $targetUser->forceDelete();

        $this->assertDatabaseMissing('pm_user_capacities', ['user_id' => $targetUser->id]);
    }
}

