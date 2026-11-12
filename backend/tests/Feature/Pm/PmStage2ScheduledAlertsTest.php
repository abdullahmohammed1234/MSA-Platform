<?php

namespace Tests\Feature\Pm;

use App\Models\Department;
use App\Models\Notification as NotificationModel;
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

class PmStage2ScheduledAlertsTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected User $otherUser;
    protected PmProject $project;

    protected function setUp(): void
    {
        parent::setUp();

        $appAccess = app(ApplicationAccessService::class);

        $this->user = User::factory()->create(['name' => 'Alert User', 'email' => 'alert@sfu.ca']);
        $this->otherUser = User::factory()->create(['name' => 'Other User', 'email' => 'other@sfu.ca']);

        $appAccess->grant($this->user, 'projects');
        $appAccess->grant($this->otherUser, 'projects');

        $dept = Department::first() ?? Department::create([
            'name' => 'General Operations',
            'code' => 'GEN',
        ]);

        $this->project = PmProject::create([
            'name' => 'Scheduled Alerts Project',
            'slug' => 'scheduled-alerts-project',
            'visibility' => ProjectVisibility::SharedMsa,
            'department_id' => $dept->id,
            'owner_id' => $this->user->id,
            'status' => ProjectStatus::InProgress,
            'priority' => ProjectPriority::Medium,
            'created_by' => $this->user->id,
        ]);

        PmProjectMember::create([
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'project_role' => ProjectRole::Owner,
            'invitation_status' => 'accepted',
        ]);
    }

    public function test_deadline_reminders_command_identifies_and_alerts_eligible_tasks()
    {
        // 1. Task due in 12 hours (24h window)
        $task24h = PmTask::create([
            'project_id' => $this->project->id,
            'title' => 'Urgent Task',
            'status' => TaskStatus::Todo,
            'priority' => ProjectPriority::High,
            'due_date' => Carbon::now()->addHours(12),
            'assigned_to' => $this->user->id,
            'created_by' => $this->user->id,
        ]);

        // 2. Task due in 36 hours (48h window)
        $task48h = PmTask::create([
            'project_id' => $this->project->id,
            'title' => 'Upcoming Task',
            'status' => TaskStatus::InProgress,
            'priority' => ProjectPriority::Medium,
            'due_date' => Carbon::now()->addHours(36),
            'assigned_to' => $this->user->id,
            'created_by' => $this->user->id,
        ]);

        // 3. Task overdue
        $taskOverdue = PmTask::create([
            'project_id' => $this->project->id,
            'title' => 'Overdue Task',
            'status' => TaskStatus::Todo,
            'priority' => ProjectPriority::Urgent,
            'due_date' => Carbon::now()->subDays(2),
            'assigned_to' => $this->user->id,
            'created_by' => $this->user->id,
        ]);

        // 4. Completed task due in 6 hours (must be excluded)
        PmTask::create([
            'project_id' => $this->project->id,
            'title' => 'Completed Task',
            'status' => TaskStatus::Completed,
            'priority' => ProjectPriority::Low,
            'due_date' => Carbon::now()->addHours(6),
            'assigned_to' => $this->user->id,
            'created_by' => $this->user->id,
        ]);

        $this->artisan('pm:send-deadline-reminders')->assertExitCode(0);

        // Verify notifications generated
        $notifications = NotificationModel::where('user_id', $this->user->id)->get();
        $this->assertCount(3, $notifications);

        $titles = $notifications->pluck('title')->all();
        $this->assertTrue(collect($titles)->contains(fn ($t) => str_contains($t, 'Urgent Task')));
        $this->assertTrue(collect($titles)->contains(fn ($t) => str_contains($t, 'Upcoming Task')));
        $this->assertTrue(collect($titles)->contains(fn ($t) => str_contains($t, 'Overdue Task')));
        $this->assertFalse(collect($titles)->contains(fn ($t) => str_contains($t, 'Completed Task')));
    }

    public function test_deadline_reminders_deduplication_prevents_duplicate_notifications()
    {
        PmTask::create([
            'project_id' => $this->project->id,
            'title' => 'Deduplication Task',
            'status' => TaskStatus::Todo,
            'priority' => ProjectPriority::High,
            'due_date' => Carbon::now()->addHours(10),
            'assigned_to' => $this->user->id,
            'created_by' => $this->user->id,
        ]);

        // Run once
        $this->artisan('pm:send-deadline-reminders')->assertSuccessful();
        $this->assertEquals(1, NotificationModel::where('user_id', $this->user->id)->count());

        // Run second time immediately
        $this->artisan('pm:send-deadline-reminders')->assertSuccessful();
        $this->assertEquals(1, NotificationModel::where('user_id', $this->user->id)->count());
    }

    public function test_daily_digest_command_generates_personalized_summary()
    {
        // Create task due today
        PmTask::create([
            'project_id' => $this->project->id,
            'title' => 'Due Today Task',
            'status' => TaskStatus::Todo,
            'priority' => ProjectPriority::High,
            'due_date' => Carbon::today(),
            'assigned_to' => $this->user->id,
            'created_by' => $this->user->id,
        ]);

        // Create upcoming milestone
        PmMilestone::create([
            'project_id' => $this->project->id,
            'title' => 'Sprint 1 Review',
            'status' => MilestoneStatus::Pending,
            'due_date' => Carbon::today()->addDays(3),
            'created_by' => $this->user->id,
        ]);

        $this->artisan('pm:send-daily-digest', ['--user' => $this->user->id])->assertSuccessful();

        $digest = NotificationModel::where('user_id', $this->user->id)
            ->where('data->type', 'pm.daily_digest')
            ->first();

        $this->assertNotNull($digest);
        $this->assertEquals('Your Daily PMS Digest', $digest->title);
        $this->assertNotEmpty($digest->data['summary']['due_today']);
        $this->assertEquals('Due Today Task', $digest->data['summary']['due_today'][0]['title']);
        $this->assertNotEmpty($digest->data['summary']['milestones']);
        $this->assertEquals('Sprint 1 Review', $digest->data['summary']['milestones'][0]['title']);

        // Running again today must be deduplicated
        $this->artisan('pm:send-daily-digest', ['--user' => $this->user->id])->assertSuccessful();
        $this->assertEquals(1, NotificationModel::where('user_id', $this->user->id)->where('data->type', 'pm.daily_digest')->count());
    }

    public function test_option_b_excludes_unauthorized_projects_from_daily_digest()
    {
        $privateDept = Department::create([
            'name' => 'Confidential Executive Dept',
            'code' => 'CONF',
        ]);

        $privateProject = PmProject::create([
            'name' => 'Executive Secret Project',
            'slug' => 'executive-secret-project',
            'visibility' => ProjectVisibility::DepartmentPrivate,
            'department_id' => $privateDept->id,
            'owner_id' => $this->otherUser->id,
            'status' => ProjectStatus::InProgress,
            'priority' => ProjectPriority::Urgent,
            'created_by' => $this->otherUser->id,
        ]);

        // Assign task to otherUser in private project
        PmTask::create([
            'project_id' => $privateProject->id,
            'title' => 'Secret Strategy Task',
            'status' => TaskStatus::Todo,
            'priority' => ProjectPriority::Urgent,
            'due_date' => Carbon::today(),
            'assigned_to' => $this->otherUser->id,
            'created_by' => $this->otherUser->id,
        ]);

        // $this->user has no access to $privateProject. Let's run digest for $this->user.
        $this->artisan('pm:send-daily-digest', ['--user' => $this->user->id])->assertSuccessful();

        $digest = NotificationModel::where('user_id', $this->user->id)
            ->where('data->type', 'pm.daily_digest')
            ->first();

        if ($digest) {
            $titles = collect($digest->data['summary']['due_today'] ?? [])->pluck('title')->all();
            $this->assertNotContains('Secret Strategy Task', $titles);
        }
    }
}
