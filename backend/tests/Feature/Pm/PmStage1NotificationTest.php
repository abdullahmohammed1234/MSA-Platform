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
use App\Pm\Notifications\MilestoneCompletedNotification;
use App\Pm\Notifications\TaskAssignedNotification;
use App\Pm\Notifications\TaskCommentMentionNotification;
use App\Pm\Notifications\TaskStatusChangedNotification;
use App\Services\ApplicationAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PmStage1NotificationTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;
    protected User $contributor;
    protected User $otherUser;
    protected PmProject $project;

    protected function setUp(): void
    {
        parent::setUp();

        $appAccess = app(ApplicationAccessService::class);

        $this->owner = User::factory()->create(['name' => 'Project Owner', 'email' => 'owner@sfu.ca']);
        $this->contributor = User::factory()->create(['name' => 'Contributor Alice', 'email' => 'alice@sfu.ca']);
        $this->otherUser = User::factory()->create(['name' => 'Other Bob', 'email' => 'bob@sfu.ca']);

        $appAccess->grant($this->owner, 'projects');
        $appAccess->grant($this->contributor, 'projects');
        $appAccess->grant($this->otherUser, 'projects');

        $dept = Department::first() ?? Department::create([
            'name' => 'General Dept',
            'code' => 'GEN',
        ]);

        $this->project = PmProject::create([
            'name' => 'Notification Test Project',
            'slug' => 'notification-test-project',
            'visibility' => ProjectVisibility::SharedMsa,
            'department_id' => $dept->id,
            'owner_id' => $this->owner->id,
            'status' => ProjectStatus::InProgress,
            'priority' => ProjectPriority::Medium,
            'created_by' => $this->owner->id,
        ]);

        PmProjectMember::create([
            'project_id' => $this->project->id,
            'user_id' => $this->owner->id,
            'project_role' => ProjectRole::Owner,
            'invitation_status' => 'accepted',
        ]);

        PmProjectMember::create([
            'project_id' => $this->project->id,
            'user_id' => $this->contributor->id,
            'project_role' => ProjectRole::Contributor,
            'invitation_status' => 'accepted',
        ]);
    }

    public function test_task_creation_with_assignee_dispatches_notification()
    {
        $response = $this->actingAs($this->owner)
            ->postJson("/api/v1/pm/projects/{$this->project->uuid}/tasks", [
                'title' => 'Initial Task',
                'priority' => 'high',
                'assigned_to' => $this->contributor->id,
                'assignee_ids' => [$this->contributor->id],
            ]);

        $response->assertStatus(201);

        $notification = NotificationModel::where('user_id', $this->contributor->id)->first();
        $this->assertNotNull($notification);
        $this->assertStringContainsString('Initial Task', $notification->title);
        $this->assertEquals('pm.task_assigned', $notification->data['type']);
        $this->assertEquals($this->project->id, $notification->data['project_id']);
        $this->assertFalse($notification->data['is_reassignment']);
    }

    public function test_self_assignment_does_not_create_self_notification()
    {
        $response = $this->actingAs($this->owner)
            ->postJson("/api/v1/pm/projects/{$this->project->uuid}/tasks", [
                'title' => 'Self Assigned Task',
                'priority' => 'medium',
                'assigned_to' => $this->owner->id,
                'assignee_ids' => [$this->owner->id],
            ]);

        $response->assertStatus(201);

        $notification = NotificationModel::where('user_id', $this->owner->id)->first();
        $this->assertNull($notification);
    }

    public function test_task_status_update_notifies_assignees()
    {
        $task = PmTask::create([
            'project_id' => $this->project->id,
            'title' => 'Status Check Task',
            'status' => TaskStatus::Todo,
            'priority' => ProjectPriority::Medium,
            'assigned_to' => $this->contributor->id,
            'created_by' => $this->owner->id,
        ]);
        $task->assignees()->attach($this->contributor->id, ['assigned_by' => $this->owner->id, 'assigned_at' => now()]);

        // Contributor changes status to in_progress
        $response = $this->actingAs($this->contributor)
            ->patchJson("/api/v1/pm/tasks/{$task->uuid}/status", [
                'status' => 'in_progress',
            ]);

        $response->assertStatus(200);

        // Owner (as creator) should receive notification, contributor should not self-notify
        $ownerNotification = NotificationModel::where('user_id', $this->owner->id)->first();
        $this->assertNotNull($ownerNotification);
        $this->assertStringContainsString('Status Check Task', $ownerNotification->title);
        $this->assertEquals('pm.task_status_changed', $ownerNotification->data['type']);
        $this->assertEquals('todo', $ownerNotification->data['old_status']);
        $this->assertEquals('in_progress', $ownerNotification->data['new_status']);

        $contributorNotification = NotificationModel::where('user_id', $this->contributor->id)->first();
        $this->assertNull($contributorNotification);
    }

    public function test_comment_mention_notifies_mentioned_user()
    {
        $response = $this->actingAs($this->owner)
            ->postJson("/api/v1/pm/projects/{$this->project->uuid}/comments", [
                'body' => 'Hey @alice@sfu.ca can you review the documentation?',
            ]);

        $response->assertStatus(201);

        $notification = NotificationModel::where('user_id', $this->contributor->id)->first();
        $this->assertNotNull($notification);
        $this->assertEquals('pm.comment_mention', $notification->data['type']);
        $this->assertStringContainsString('Project Owner', $notification->title);
        $this->assertStringContainsString('review the documentation', $notification->message);
    }

    public function test_comment_mention_suppresses_notification_for_unauthorized_user()
    {
        // Create department-private project where otherUser has NO access
        $dept = Department::first() ?? Department::create([
            'name' => 'Internal Dept',
            'code' => 'INT',
        ]);

        $privateProject = PmProject::create([
            'name' => 'Private Secure Project',
            'slug' => 'private-secure-project',
            'visibility' => ProjectVisibility::DepartmentPrivate,
            'department_id' => $dept->id,
            'owner_id' => $this->owner->id,
            'status' => ProjectStatus::InProgress,
            'priority' => ProjectPriority::Medium,
            'created_by' => $this->owner->id,
        ]);

        PmProjectMember::create([
            'project_id' => $privateProject->id,
            'user_id' => $this->owner->id,
            'project_role' => ProjectRole::Owner,
            'invitation_status' => 'accepted',
        ]);

        // Owner mentions bob (otherUser) who is NOT a member of dept or project
        $response = $this->actingAs($this->owner)
            ->postJson("/api/v1/pm/projects/{$privateProject->uuid}/comments", [
                'body' => 'Secret project discussion with @bob@sfu.ca',
            ]);

        $response->assertStatus(201);

        // Bob must NOT receive any notification due to Option B
        $bobNotification = NotificationModel::where('user_id', $this->otherUser->id)->first();
        $this->assertNull($bobNotification);
    }

    public function test_milestone_completion_notifies_project_members()
    {
        $milestone = PmMilestone::create([
            'project_id' => $this->project->id,
            'title' => 'Alpha Launch',
            'status' => MilestoneStatus::Pending,
            'created_by' => $this->owner->id,
        ]);

        $response = $this->actingAs($this->owner)
            ->putJson("/api/v1/pm/milestones/{$milestone->uuid}", [
                'status' => 'reached',
            ]);

        $response->assertStatus(200);

        // Contributor was a member of the project, should be notified
        $notification = NotificationModel::where('user_id', $this->contributor->id)->first();
        $this->assertNotNull($notification);
        $this->assertEquals('pm.milestone_completed', $notification->data['type']);
        $this->assertStringContainsString('Alpha Launch', $notification->title);
    }

    public function test_notification_classes_via_channel_configuration()
    {
        $task = PmTask::create([
            'project_id' => $this->project->id,
            'title' => 'Channel Test Task',
            'status' => TaskStatus::Todo,
            'priority' => ProjectPriority::Medium,
            'created_by' => $this->owner->id,
        ]);

        $milestone = PmMilestone::create([
            'project_id' => $this->project->id,
            'title' => 'Milestone Channels',
            'status' => MilestoneStatus::Pending,
            'created_by' => $this->owner->id,
        ]);

        $comment = \App\Pm\Models\PmComment::create([
            'project_id' => $this->project->id,
            'user_id' => $this->owner->id,
            'body' => 'Hello',
        ]);

        $taskAssigned = new TaskAssignedNotification($task, $this->project, $this->owner);
        $this->assertContains(\App\Services\Notifications\CustomDatabaseChannel::class, $taskAssigned->via($this->contributor));
        $this->assertContains('mail', $taskAssigned->via($this->contributor));

        $statusChanged = new TaskStatusChangedNotification($task, $this->project, TaskStatus::Todo, TaskStatus::InProgress, $this->owner);
        $this->assertEquals([\App\Services\Notifications\CustomDatabaseChannel::class], $statusChanged->via($this->contributor));

        $mention = new TaskCommentMentionNotification($comment, $this->project, $task, $this->owner);
        $this->assertContains(\App\Services\Notifications\CustomDatabaseChannel::class, $mention->via($this->contributor));
        $this->assertContains('mail', $mention->via($this->contributor));

        $milestoneCompleted = new MilestoneCompletedNotification($milestone, $this->project, $this->owner);
        $this->assertEquals([\App\Services\Notifications\CustomDatabaseChannel::class], $milestoneCompleted->via($this->contributor));
    }
}
