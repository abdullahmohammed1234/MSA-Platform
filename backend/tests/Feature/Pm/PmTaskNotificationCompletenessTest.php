<?php

namespace Tests\Feature\Pm;

use App\Models\Department;
use App\Models\Notification as NotificationModel;
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
use App\Pm\Notifications\TaskCompletedNotification;
use App\Pm\Notifications\TaskDeadlineChangedNotification;
use App\Pm\Notifications\TaskReopenedNotification;
use App\Pm\Support\PmFrontendUrl;
use App\Services\ApplicationAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PmTaskNotificationCompletenessTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;
    protected User $creator;
    protected User $assignee;
    protected User $secondAssignee;
    protected Department $department;
    protected PmProject $project;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.projects_url' => 'https://projects.sfumsa.ca']);

        $appAccess = app(ApplicationAccessService::class);

        $this->owner = User::factory()->create(['name' => 'Canonical Owner', 'email' => 'owner@sfu.ca']);
        $this->creator = User::factory()->create(['name' => 'Task Creator', 'email' => 'creator@sfu.ca']);
        $this->assignee = User::factory()->create(['name' => 'Assignee One', 'email' => 'a1@sfu.ca']);
        $this->secondAssignee = User::factory()->create(['name' => 'Assignee Two', 'email' => 'a2@sfu.ca']);

        foreach ([$this->owner, $this->creator, $this->assignee, $this->secondAssignee] as $user) {
            $appAccess->grant($user, 'projects');
        }

        $this->department = Department::create([
            'name' => 'Notifications Dept',
            'code' => 'NOTIF',
            'slug' => 'notifications-dept',
            'is_active' => true,
        ]);

        // Owner ≠ creator to certify owner_id targeting.
        $this->project = PmProject::create([
            'name' => 'Notification Completeness Project',
            'slug' => 'notification-completeness-project',
            'visibility' => ProjectVisibility::SharedMsa,
            'department_id' => $this->department->id,
            'owner_id' => $this->owner->id,
            'status' => ProjectStatus::InProgress,
            'priority' => ProjectPriority::Medium,
            'created_by' => $this->creator->id,
        ]);

        foreach ([
            [$this->owner, ProjectRole::Owner],
            [$this->creator, ProjectRole::Manager],
            [$this->assignee, ProjectRole::Contributor],
            [$this->secondAssignee, ProjectRole::Contributor],
        ] as [$user, $role]) {
            PmProjectMember::create([
                'project_id' => $this->project->id,
                'user_id' => $user->id,
                'project_role' => $role,
                'invitation_status' => 'accepted',
            ]);
        }
    }

    #[Test]
    public function assignment_notification_uses_projects_frontend_url(): void
    {
        $notification = new TaskAssignedNotification(
            PmTask::create([
                'project_id' => $this->project->id,
                'title' => 'Link Check Task',
                'department_id' => $this->department->id,
                'created_by' => $this->creator->id,
                'priority' => ProjectPriority::Medium,
            ])->load('department'),
            $this->project,
            $this->creator,
            false
        );

        $mail = $notification->toMail($this->assignee);
        $this->assertSame(
            'https://projects.sfumsa.ca/projects/' . $this->project->uuid,
            $mail->actionUrl
        );
        $this->assertSame(
            PmFrontendUrl::projectPath($this->project->uuid),
            $notification->toArray($this->assignee)['action_url']
        );
    }

    #[Test]
    public function create_assignment_notifies_new_assignee_but_not_actor(): void
    {
        $response = $this->actingAs($this->creator)->postJson(
            "/api/v1/pm/projects/{$this->project->uuid}/tasks",
            [
                'title' => 'Assigned Task',
                'assignee_ids' => [$this->creator->id, $this->assignee->id],
                'assigned_to' => $this->assignee->id,
            ]
        );
        $response->assertCreated();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->assignee->id,
            'title' => 'Task Assigned: Assigned Task',
        ]);
        $this->assertDatabaseMissing('notifications', [
            'user_id' => $this->creator->id,
            'title' => 'Task Assigned: Assigned Task',
        ]);
    }

    #[Test]
    public function reassignment_notifies_only_newly_added_assignees(): void
    {
        $create = $this->actingAs($this->creator)->postJson(
            "/api/v1/pm/projects/{$this->project->uuid}/tasks",
            [
                'title' => 'Reassign Task',
                'assignee_ids' => [$this->assignee->id],
                'assigned_to' => $this->assignee->id,
            ]
        )->assertCreated();

        NotificationModel::query()->delete();

        $this->actingAs($this->creator)->putJson("/api/v1/pm/tasks/{$create->json('task.uuid')}", [
            'assignee_ids' => [$this->assignee->id, $this->secondAssignee->id],
            'assigned_to' => $this->assignee->id,
        ])->assertOk();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->secondAssignee->id,
        ]);
        $this->assertDatabaseMissing('notifications', [
            'user_id' => $this->assignee->id,
        ]);
    }

    #[Test]
    public function completion_notifies_canonical_project_owner_not_task_creator(): void
    {
        $task = PmTask::create([
            'project_id' => $this->project->id,
            'title' => 'Complete Me',
            'status' => TaskStatus::InProgress,
            'priority' => ProjectPriority::Medium,
            'created_by' => $this->creator->id,
            'assigned_to' => $this->assignee->id,
            'department_id' => $this->department->id,
        ]);
        $task->assignees()->sync([$this->assignee->id]);

        $this->actingAs($this->assignee)->patchJson("/api/v1/pm/tasks/{$task->uuid}/status", [
            'status' => 'completed',
        ])->assertOk();

        $ownerNotif = NotificationModel::where('user_id', $this->owner->id)
            ->where('data->type', 'pm.task_completed')
            ->first();
        $this->assertNotNull($ownerNotif);
        $this->assertStringContainsString('Complete Me', $ownerNotif->title);

        $this->assertDatabaseMissing('notifications', [
            'user_id' => $this->creator->id,
            'title' => 'Task Completed: Complete Me',
        ]);
    }

    #[Test]
    public function reopening_notifies_canonical_project_owner(): void
    {
        $task = PmTask::create([
            'project_id' => $this->project->id,
            'title' => 'Reopen Me',
            'status' => TaskStatus::Completed,
            'completed_at' => now(),
            'priority' => ProjectPriority::Medium,
            'created_by' => $this->creator->id,
        ]);

        $this->actingAs($this->creator)->patchJson("/api/v1/pm/tasks/{$task->uuid}/status", [
            'status' => 'todo',
        ])->assertOk();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->owner->id,
            'title' => 'Task Reopened: Reopen Me',
        ]);
    }

    #[Test]
    public function unchanged_status_does_not_duplicate_transition_notifications(): void
    {
        $task = PmTask::create([
            'project_id' => $this->project->id,
            'title' => 'Already Complete',
            'status' => TaskStatus::Completed,
            'completed_at' => now(),
            'priority' => ProjectPriority::Medium,
            'created_by' => $this->creator->id,
        ]);

        $this->actingAs($this->assignee)->patchJson("/api/v1/pm/tasks/{$task->uuid}/status", [
            'status' => 'completed',
        ])->assertOk();

        $this->assertSame(
            0,
            NotificationModel::where('data->type', 'pm.task_completed')->count()
        );
    }

    #[Test]
    public function deadline_set_change_and_removal_notify_assignees(): void
    {
        $create = $this->actingAs($this->creator)->postJson(
            "/api/v1/pm/projects/{$this->project->uuid}/tasks",
            [
                'title' => 'Deadline Task',
                'assignee_ids' => [$this->assignee->id],
                'assigned_to' => $this->assignee->id,
            ]
        )->assertCreated();

        NotificationModel::query()->delete();
        $uuid = $create->json('task.uuid');

        $this->actingAs($this->creator)->putJson("/api/v1/pm/tasks/{$uuid}", [
            'due_date' => '2030-01-15',
        ])->assertOk();
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->assignee->id,
            'title' => 'Deadline Set: Deadline Task',
        ]);

        NotificationModel::query()->delete();
        $this->actingAs($this->creator)->putJson("/api/v1/pm/tasks/{$uuid}", [
            'due_date' => '2030-02-01',
        ])->assertOk();
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->assignee->id,
            'title' => 'Deadline Changed: Deadline Task',
        ]);

        NotificationModel::query()->delete();
        $this->actingAs($this->creator)->putJson("/api/v1/pm/tasks/{$uuid}", [
            'due_date' => null,
        ])->assertOk();
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->assignee->id,
            'title' => 'Deadline Removed: Deadline Task',
        ]);

        NotificationModel::query()->delete();
        $this->actingAs($this->creator)->putJson("/api/v1/pm/tasks/{$uuid}", [
            'title' => 'Deadline Task Updated',
            'due_date' => null,
        ])->assertOk();
        $this->assertSame(0, NotificationModel::where('data->type', 'pm.task_deadline_changed')->count());
    }

    #[Test]
    public function bulk_assign_does_not_renotify_existing_assignees(): void
    {
        $create = $this->actingAs($this->creator)->postJson(
            "/api/v1/pm/projects/{$this->project->uuid}/tasks",
            [
                'title' => 'Bulk Assign Task',
                'assignee_ids' => [$this->assignee->id],
                'assigned_to' => $this->assignee->id,
            ]
        )->assertCreated();

        NotificationModel::query()->delete();

        $this->actingAs($this->creator)->postJson('/api/v1/pm/tasks/bulk', [
            'project_uuid' => $this->project->uuid,
            'task_uuids' => [$create->json('task.uuid')],
            'action' => 'assign',
            'value' => [$this->assignee->id, $this->secondAssignee->id],
        ])->assertOk();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->secondAssignee->id,
        ]);
        $this->assertDatabaseMissing('notifications', [
            'user_id' => $this->assignee->id,
        ]);
    }

    #[Test]
    public function mention_supports_multi_word_project_member_names(): void
    {
        $task = PmTask::create([
            'project_id' => $this->project->id,
            'title' => 'Mention Task',
            'priority' => ProjectPriority::Medium,
            'created_by' => $this->creator->id,
        ]);

        $this->actingAs($this->creator)->postJson(
            "/api/v1/pm/projects/{$this->project->uuid}/comments",
            [
                'task_uuid' => $task->uuid,
                'body' => 'Hello @Assignee One please review',
            ]
        )->assertCreated();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->assignee->id,
        ]);
        $mention = NotificationModel::where('user_id', $this->assignee->id)->latest('id')->first();
        $this->assertSame('pm.comment_mention', $mention->data['type']);
        $this->assertSame('/projects/' . $this->project->uuid, $mention->data['action_url']);
    }
}
