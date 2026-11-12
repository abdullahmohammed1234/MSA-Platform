<?php

namespace Tests\Feature\Pm;

use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use App\Pm\Enums\DependencyType;
use App\Pm\Enums\ProjectPriority;
use App\Pm\Enums\ProjectStatus;
use App\Pm\Enums\ProjectVisibility;
use App\Pm\Models\PmCalendarFeed;
use App\Pm\Models\PmProject;
use App\Pm\Models\PmTask;
use App\Pm\Models\PmTaskDependency;
use Database\Seeders\PmDepartmentSeeder;
use Database\Seeders\PmRolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class PmSchedulingStage1SchemaAndModelsTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Department $itDept;
    protected PmProject $project;
    protected PmTask $taskA;
    protected PmTask $taskB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PmDepartmentSeeder::class);
        $this->seed(PmRolePermissionSeeder::class);

        $this->itDept = Department::where('slug', 'it')->firstOrFail();
        $memberRole = Role::firstOrCreate(['slug' => 'member'], ['uuid' => (string) \Illuminate\Support\Str::uuid(), 'name' => 'Member']);

        $this->user = User::factory()->create([
            'name' => 'Stage 1 User',
            'email' => 'stage1@sfumsa.ca',
            'is_active' => true,
        ]);
        $this->user->roles()->sync([$memberRole->id]);

        $this->project = PmProject::create([
            'department_id' => $this->itDept->id,
            'name' => 'Scheduling Test Project',
            'visibility' => ProjectVisibility::DepartmentPrivate,
            'status' => ProjectStatus::InProgress,
            'priority' => ProjectPriority::Medium,
            'owner_id' => $this->user->id,
        ]);

        $this->taskA = PmTask::create([
            'project_id' => $this->project->id,
            'title' => 'Task A (Predecessor)',
            'start_date' => '2026-11-10',
            'due_date' => '2026-11-15',
            'created_by' => $this->user->id,
        ]);

        $this->taskB = PmTask::create([
            'project_id' => $this->project->id,
            'title' => 'Task B (Successor)',
            'start_date' => '2026-11-16',
            'due_date' => '2026-11-20',
            'created_by' => $this->user->id,
        ]);
    }

    public function test_dependency_defaults_to_finish_to_start_and_zero_lag(): void
    {
        $dep = PmTaskDependency::create([
            'project_id' => $this->project->id,
            'task_id' => $this->taskB->id,
            'depends_on_task_id' => $this->taskA->id,
            'created_by' => $this->user->id,
        ]);

        $this->assertDatabaseHas('pm_task_dependencies', [
            'id' => $dep->id,
            'dependency_type' => 'finish_to_start',
            'lag_days' => 0,
        ]);

        $this->assertInstanceOf(DependencyType::class, $dep->dependency_type);
        $this->assertEquals(DependencyType::FinishToStart, $dep->dependency_type);
        $this->assertSame(0, $dep->lag_days);
        $this->assertTrue($dep->isFinishToStart());
        $this->assertFalse($dep->isStartToStart());
        $this->assertFalse($dep->isFinishToFinish());
    }

    public function test_dependency_supports_start_to_start_and_finish_to_finish_with_lag(): void
    {
        $depSS = PmTaskDependency::create([
            'project_id' => $this->project->id,
            'task_id' => $this->taskB->id,
            'depends_on_task_id' => $this->taskA->id,
            'dependency_type' => DependencyType::StartToStart,
            'lag_days' => 3, // Positive lag
            'created_by' => $this->user->id,
        ]);

        $this->assertEquals(DependencyType::StartToStart, $depSS->dependency_type);
        $this->assertSame(3, $depSS->lag_days);
        $this->assertTrue($depSS->isStartToStart());

        // Update to finish to finish with negative lag (lead time)
        $depSS->update([
            'dependency_type' => DependencyType::FinishToFinish,
            'lag_days' => -2, // Negative lag (lead)
        ]);

        $depSS->refresh();
        $this->assertEquals(DependencyType::FinishToFinish, $depSS->dependency_type);
        $this->assertSame(-2, $depSS->lag_days);
        $this->assertTrue($depSS->isFinishToFinish());
    }

    public function test_task_pivot_includes_dependency_type_and_lag_days(): void
    {
        PmTaskDependency::create([
            'project_id' => $this->project->id,
            'task_id' => $this->taskB->id,
            'depends_on_task_id' => $this->taskA->id,
            'dependency_type' => DependencyType::StartToStart,
            'lag_days' => 4,
            'created_by' => $this->user->id,
        ]);

        $this->taskB->load('dependencies');
        $this->assertCount(1, $this->taskB->dependencies);

        $pivot = $this->taskB->dependencies->first()->pivot;
        $this->assertEquals('start_to_start', $pivot->dependency_type);
        $this->assertEquals(4, $pivot->lag_days);

        $this->taskA->load('dependents');
        $this->assertCount(1, $this->taskA->dependents);
        $dependentPivot = $this->taskA->dependents->first()->pivot;
        $this->assertEquals('start_to_start', $dependentPivot->dependency_type);
        $this->assertEquals(4, $dependentPivot->lag_days);
    }

    public function test_calendar_feed_creation_uuid_generation_and_relationships(): void
    {
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);

        $feed = PmCalendarFeed::create([
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
            'token_hash' => $tokenHash,
            'name' => 'Project Timeline Feed',
            'include_tasks' => true,
            'include_milestones' => true,
            'include_project_deadlines' => false,
            'only_assigned_to_me' => true,
        ]);

        $this->assertNotEmpty($feed->uuid);
        $this->assertTrue(\Illuminate\Support\Str::isUuid($feed->uuid));
        $this->assertEquals($this->user->id, $feed->user->id);
        $this->assertEquals($this->project->id, $feed->project->id);
        $this->assertTrue($feed->include_tasks);
        $this->assertTrue($feed->include_milestones);
        $this->assertFalse($feed->include_project_deadlines);
        $this->assertTrue($feed->only_assigned_to_me);
        $this->assertFalse($feed->isRevoked());
    }

    public function test_calendar_feed_supports_global_null_project_scope(): void
    {
        $feed = PmCalendarFeed::create([
            'user_id' => $this->user->id,
            'project_id' => null, // Global user feed across all accessible projects
            'token_hash' => hash('sha256', 'dummy_token_123'),
            'name' => 'All My Accessible Projects Feed',
        ]);

        $this->assertNull($feed->project_id);
        $this->assertNull($feed->project);
        $this->assertEquals($this->user->id, $feed->user->id);
    }

    public function test_calendar_feed_revocation_and_access_logging(): void
    {
        $feed = PmCalendarFeed::create([
            'user_id' => $this->user->id,
            'token_hash' => hash('sha256', 'feed_token_xyz'),
            'name' => 'Active Feed',
        ]);

        $this->assertFalse($feed->isRevoked());
        $this->assertCount(1, PmCalendarFeed::active()->get());

        // Record access
        $feed->recordAccess('192.168.1.100');
        $feed->refresh();
        $this->assertNotNull($feed->last_accessed_at);
        $this->assertEquals('192.168.1.100', $feed->last_accessed_ip);

        // Revoke feed
        $feed->revoke();
        $feed->refresh();
        $this->assertTrue($feed->isRevoked());
        $this->assertNotNull($feed->revoked_at);
        $this->assertCount(0, PmCalendarFeed::active()->get());
    }

    public function test_calendar_feed_never_serializes_token_hash(): void
    {
        $feed = PmCalendarFeed::create([
            'user_id' => $this->user->id,
            'token_hash' => hash('sha256', 'secret_token_never_leak'),
            'name' => 'Secure Feed',
        ]);

        $array = $feed->toArray();
        $this->assertArrayNotHasKey('token_hash', $array);

        $json = json_encode($feed);
        $this->assertStringNotContainsString('token_hash', (string) $json);
        $this->assertStringNotContainsString('secret_token_never_leak', (string) $json);
    }

    public function test_cascade_deletion_on_user_and_project_delete(): void
    {
        $feed = PmCalendarFeed::create([
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
            'token_hash' => hash('sha256', 'cascade_token'),
            'name' => 'Cascade Feed',
        ]);

        // When project is deleted, project-scoped feed is cascade deleted
        $this->project->forceDelete();
        $this->assertDatabaseMissing('pm_calendar_feeds', ['id' => $feed->id]);

        // Create feed without project
        $userFeed = PmCalendarFeed::create([
            'user_id' => $this->user->id,
            'project_id' => null,
            'token_hash' => hash('sha256', 'user_cascade_token'),
            'name' => 'User Cascade Feed',
        ]);

        // When user is force deleted, user feeds are cascade deleted
        $this->user->forceDelete();
        $this->assertDatabaseMissing('pm_calendar_feeds', ['id' => $userFeed->id]);
    }

    public function test_migration_rollback_and_remigration(): void
    {
        // Rollback the 2 Phase 12 migrations
        $rollbackExit = Artisan::call('migrate:rollback', ['--step' => 2]);
        $this->assertEquals(0, $rollbackExit);

        // Re-run migrations
        $migrateExit = Artisan::call('migrate');
        $this->assertEquals(0, $migrateExit);

        // Verify tables and columns are restored cleanly
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasColumn('pm_task_dependencies', 'dependency_type'));
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasColumn('pm_task_dependencies', 'lag_days'));
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('pm_calendar_feeds'));
    }
}
