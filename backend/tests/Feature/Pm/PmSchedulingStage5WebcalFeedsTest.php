<?php

namespace Tests\Feature\Pm;

use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use App\Pm\Enums\DepartmentRole;
use App\Pm\Enums\MilestoneStatus;
use App\Pm\Enums\ProjectPriority;
use App\Pm\Enums\ProjectStatus;
use App\Pm\Enums\ProjectVisibility;
use App\Pm\Enums\TaskStatus;
use App\Pm\Models\PmCalendarFeed;
use App\Pm\Models\PmMilestone;
use App\Pm\Models\PmProject;
use App\Pm\Models\PmTask;
use App\Pm\Services\DepartmentMembershipService;
use App\Services\ApplicationAccessService;
use Database\Seeders\PmDepartmentSeeder;
use Database\Seeders\PmRolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PmSchedulingStage5WebcalFeedsTest extends TestCase
{
    use RefreshDatabase;

    protected ApplicationAccessService $appAccessService;
    protected DepartmentMembershipService $deptService;

    protected User $itMember;
    protected User $itSecondary;
    protected User $eventsMember;
    protected Department $itDept;
    protected Department $eventsDept;
    protected PmProject $itProject;
    protected PmProject $eventsProject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PmDepartmentSeeder::class);
        $this->seed(PmRolePermissionSeeder::class);

        $this->appAccessService = app(ApplicationAccessService::class);
        $this->deptService = app(DepartmentMembershipService::class);

        $this->itDept = Department::where('slug', 'it')->firstOrFail();
        $this->eventsDept = Department::where('slug', 'events')->firstOrFail();

        $memberRole = Role::firstOrCreate(['slug' => 'member'], ['uuid' => (string) Str::uuid(), 'name' => 'Member']);

        $this->itMember = User::factory()->create([
            'email' => 'it.member@sfumsa.ca',
            'is_active' => true,
        ]);
        $this->itMember->roles()->sync([$memberRole->id]);
        $this->deptService->addMember($this->itDept, $this->itMember, DepartmentRole::Member);
        $this->appAccessService->grant($this->itMember, 'projects', $this->itMember);

        $this->itSecondary = User::factory()->create([
            'email' => 'it.secondary@sfumsa.ca',
            'is_active' => true,
        ]);
        $this->itSecondary->roles()->sync([$memberRole->id]);
        $this->deptService->addMember($this->itDept, $this->itSecondary, DepartmentRole::Member);
        $this->appAccessService->grant($this->itSecondary, 'projects', $this->itSecondary);

        $this->eventsMember = User::factory()->create([
            'email' => 'events.member@sfumsa.ca',
            'is_active' => true,
        ]);
        $this->eventsMember->roles()->sync([$memberRole->id]);
        $this->deptService->addMember($this->eventsDept, $this->eventsMember, DepartmentRole::Member);
        $this->appAccessService->grant($this->eventsMember, 'projects', $this->eventsMember);

        $this->itProject = PmProject::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'IT Infrastructure Overhaul',
            'code' => 'ITO',
            'department_id' => $this->itDept->id,
            'visibility' => ProjectVisibility::DepartmentPrivate,
            'status' => ProjectStatus::InProgress,
            'priority' => ProjectPriority::High,
            'owner_id' => $this->itMember->id,
            'created_by' => $this->itMember->id,
            'due_date' => '2026-12-31',
        ]);

        $this->eventsProject = PmProject::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Annual Gala 2026',
            'code' => 'GALA',
            'department_id' => $this->eventsDept->id,
            'visibility' => ProjectVisibility::DepartmentPrivate,
            'status' => ProjectStatus::InProgress,
            'priority' => ProjectPriority::Medium,
            'owner_id' => $this->eventsMember->id,
            'created_by' => $this->eventsMember->id,
            'due_date' => '2026-11-20',
        ]);
    }

    public function test_user_can_create_calendar_feed_with_single_token_disclosure(): void
    {
        Sanctum::actingAs($this->itMember);

        $response = $this->postJson('/api/v1/pm/calendar/feeds', [
            'name' => 'My Personal Work Feed',
            'include_tasks' => true,
            'include_milestones' => true,
            'include_project_deadlines' => true,
            'only_assigned_to_me' => true,
        ]);

        $response->assertStatus(201);
        $data = $response->json('data');

        $this->assertEquals('My Personal Work Feed', $data['name']);
        $this->assertNotEmpty($data['plain_token']);
        $this->assertEquals(64, strlen($data['plain_token']));
        $this->assertStringStartsWith('webcal://', $data['webcal_url']);
        $this->assertStringContainsString($data['plain_token'], $data['webcal_url']);
        $this->assertStringStartsWith('http', $data['https_url']);

        // Check database storage: only SHA-256 hash is stored
        $feed = PmCalendarFeed::where('uuid', $data['uuid'])->firstOrFail();
        $this->assertEquals(hash('sha256', $data['plain_token']), $feed->token_hash);

        // token_hash is never exposed in response
        $this->assertArrayNotHasKey('token_hash', $data);
    }

    public function test_user_cannot_create_feed_for_inaccessible_project(): void
    {
        Sanctum::actingAs($this->itMember);

        // Attempt to create feed scoped to Events private project
        $response = $this->postJson('/api/v1/pm/calendar/feeds', [
            'name' => 'Spying Feed',
            'project_uuid' => $this->eventsProject->uuid,
        ]);

        $response->assertStatus(403);
    }

    public function test_user_can_list_active_feeds_without_token_exposure(): void
    {
        Sanctum::actingAs($this->itMember);

        $plainToken = bin2hex(random_bytes(32));
        $feed = PmCalendarFeed::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $this->itMember->id,
            'token_hash' => hash('sha256', $plainToken),
            'name' => 'Existing Feed',
            'include_tasks' => true,
        ]);

        $response = $this->getJson('/api/v1/pm/calendar/feeds');
        $response->assertOk();

        $feeds = $response->json('data');
        $this->assertCount(1, $feeds);
        $this->assertEquals($feed->uuid, $feeds[0]['uuid']);
        $this->assertArrayNotHasKey('token_hash', $feeds[0]);
        $this->assertArrayNotHasKey('plain_token', $feeds[0]);
    }

    public function test_user_can_revoke_feed(): void
    {
        Sanctum::actingAs($this->itMember);

        $plainToken = bin2hex(random_bytes(32));
        $feed = PmCalendarFeed::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $this->itMember->id,
            'token_hash' => hash('sha256', $plainToken),
            'name' => 'Revokable Feed',
        ]);

        $response = $this->deleteJson("/api/v1/pm/calendar/feeds/{$feed->uuid}");
        $response->assertOk();

        $this->assertTrue($feed->fresh()->isRevoked());

        // Revoked feeds no longer appear in list
        $listRes = $this->getJson('/api/v1/pm/calendar/feeds');
        $listRes->assertOk();
        $this->assertEmpty($listRes->json('data'));
    }

    public function test_public_feed_endpoint_streams_ics_and_records_access(): void
    {
        $plainToken = bin2hex(random_bytes(32));
        $feed = PmCalendarFeed::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $this->itMember->id,
            'token_hash' => hash('sha256', $plainToken),
            'name' => 'Public Subscription Feed',
            'include_tasks' => true,
            'include_milestones' => true,
            'include_project_deadlines' => true,
            'only_assigned_to_me' => false,
        ]);

        $task = PmTask::create([
            'uuid' => (string) Str::uuid(),
            'project_id' => $this->itProject->id,
            'title' => 'Core Router Firmware Upgrade',
            'status' => TaskStatus::InProgress,
            'priority' => 'high',
            'due_date' => '2026-11-20',
            'created_by' => $this->itMember->id,
        ]);

        // Access via unauthenticated public GET request
        $response = $this->get("/api/v1/pm/calendar/feed/{$plainToken}.ics");
        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/calendar; charset=utf-8');

        $ics = $response->getContent();
        $this->assertStringContainsString("Core Router Firmware Upgrade", $ics);
        $this->assertStringContainsString("task-{$task->uuid}@projects.sfumsa.ca", $ics);

        // Verify access logging
        $feed->refresh();
        $this->assertNotNull($feed->last_accessed_at);
        $this->assertNotNull($feed->last_accessed_ip);
    }

    public function test_public_feed_endpoint_rejects_revoked_or_invalid_token(): void
    {
        $plainToken = bin2hex(random_bytes(32));
        $feed = PmCalendarFeed::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $this->itMember->id,
            'token_hash' => hash('sha256', $plainToken),
            'name' => 'Revoked Feed',
            'revoked_at' => now(),
        ]);

        // Revoked token
        $this->get("/api/v1/pm/calendar/feed/{$plainToken}.ics")->assertStatus(404);

        // Non-existent token
        $fakeToken = bin2hex(random_bytes(32));
        $this->get("/api/v1/pm/calendar/feed/{$fakeToken}.ics")->assertStatus(404);
    }

    public function test_public_feed_rejects_deactivated_user_or_revoked_entitlement(): void
    {
        $plainToken = bin2hex(random_bytes(32));
        $feed = PmCalendarFeed::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $this->itMember->id,
            'token_hash' => hash('sha256', $plainToken),
            'name' => 'User Feed',
        ]);

        // 1. Deactivated user
        $this->itMember->update(['is_active' => false]);
        $this->get("/api/v1/pm/calendar/feed/{$plainToken}.ics")->assertStatus(404);

        // Reactivate
        $this->itMember->update(['is_active' => true]);

        // 2. Revoked entitlement
        $this->appAccessService->revoke($this->itMember, 'projects', $this->itMember);
        $this->get("/api/v1/pm/calendar/feed/{$plainToken}.ics")->assertStatus(403);
    }

    public function test_public_feed_honors_only_assigned_to_me_and_option_b(): void
    {
        $plainToken = bin2hex(random_bytes(32));
        $feed = PmCalendarFeed::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $this->itMember->id,
            'token_hash' => hash('sha256', $plainToken),
            'name' => 'My Tasks Only Feed',
            'include_tasks' => true,
            'include_milestones' => false,
            'include_project_deadlines' => false,
            'only_assigned_to_me' => true,
        ]);

        // Task 1: assigned to itMember
        $taskMine = PmTask::create([
            'uuid' => (string) Str::uuid(),
            'project_id' => $this->itProject->id,
            'title' => 'Assigned To Abdul',
            'assigned_to' => $this->itMember->id,
            'due_date' => '2026-11-20',
            'created_by' => $this->itMember->id,
        ]);

        // Task 2: assigned to itSecondary
        $taskOther = PmTask::create([
            'uuid' => (string) Str::uuid(),
            'project_id' => $this->itProject->id,
            'title' => 'Assigned To Colleague',
            'assigned_to' => $this->itSecondary->id,
            'due_date' => '2026-11-20',
            'created_by' => $this->itSecondary->id,
        ]);

        // Task 3: In Events department private project
        $taskEvents = PmTask::create([
            'uuid' => (string) Str::uuid(),
            'project_id' => $this->eventsProject->id,
            'title' => 'Events Secret Task',
            'assigned_to' => $this->eventsMember->id,
            'due_date' => '2026-11-20',
            'created_by' => $this->eventsMember->id,
        ]);

        $response = $this->get("/api/v1/pm/calendar/feed/{$plainToken}.ics");
        $response->assertOk();

        $ics = $response->getContent();
        $this->assertStringContainsString("Assigned To Abdul", $ics);
        $this->assertStringNotContainsString("Assigned To Colleague", $ics);
        $this->assertStringNotContainsString("Events Secret Task", $ics);
    }
}

