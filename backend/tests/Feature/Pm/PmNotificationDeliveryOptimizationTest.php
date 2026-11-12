<?php

namespace Tests\Feature\Pm;

use App\Models\Department;
use App\Models\Notification as NotificationModel;
use App\Models\NotificationPreference;
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
use App\Pm\Services\DepartmentMembershipService;
use App\Pm\Services\PmNotificationService;
use App\Services\ApplicationAccessService;
use Carbon\Carbon;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Channels\MailChannel;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PmNotificationDeliveryOptimizationTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;
    protected User $assignee;
    protected Department $department;
    protected PmProject $project;
    protected PmTask $task;
    protected PmNotificationService $notificationService;

    protected function setUp(): void
    {
        parent::setUp();

        $appAccess = app(ApplicationAccessService::class);
        $this->owner = User::factory()->create(['name' => 'Project Lead', 'email' => 'lead@sfu.ca']);
        $this->assignee = User::factory()->create(['name' => 'Task Assignee', 'email' => 'worker@sfu.ca']);

        $appAccess->grant($this->owner, 'projects');
        $appAccess->grant($this->assignee, 'projects');

        $this->department = Department::create(['name' => 'Technical Operations', 'code' => 'TECH']);

        $this->project = PmProject::create([
            'name' => 'Mission Critical Infrastructure',
            'slug' => 'mission-critical-infrastructure',
            'code' => 'TECH-01',
            'visibility' => ProjectVisibility::DepartmentPrivate,
            'department_id' => $this->department->id,
            'owner_id' => $this->owner->id,
            'status' => ProjectStatus::InProgress,
            'priority' => ProjectPriority::High,
            'created_by' => $this->owner->id,
        ]);

        $deptService = app(DepartmentMembershipService::class);
        $deptService->addMember($this->department, $this->owner);
        $deptService->addMember($this->department, $this->assignee);

        PmProjectMember::create([
            'project_id' => $this->project->id,
            'user_id' => $this->owner->id,
            'project_role' => ProjectRole::Owner,
            'invitation_status' => 'accepted',
        ]);

        PmProjectMember::create([
            'project_id' => $this->project->id,
            'user_id' => $this->assignee->id,
            'project_role' => ProjectRole::Contributor,
            'invitation_status' => 'accepted',
        ]);

        $this->task = PmTask::create([
            'project_id' => $this->project->id,
            'title' => 'Server Security Audit',
            'status' => TaskStatus::InProgress,
            'priority' => ProjectPriority::High,
            'assigned_to' => $this->assignee->id,
            'created_by' => $this->owner->id,
            'due_date' => Carbon::now()->addDays(2),
        ]);

        $this->notificationService = app(PmNotificationService::class);
    }

    #[Test]
    public function in_app_notification_is_persisted_even_if_smtp_transport_throws_exception(): void
    {
        // Mock MailChannel to throw an SMTP connection exception
        $mockMailChannel = $this->createMock(MailChannel::class);
        $mockMailChannel->method('send')
            ->willThrowException(new Exception('SMTP Connection timed out: host unreachable on port 587'));

        $this->app->instance(MailChannel::class, $mockMailChannel);

        Log::shouldReceive('error')
            ->once()
            ->withArgs(function ($message, $context) {
                return str_contains($message, 'PmNotificationService mail delivery exception')
                    && str_contains($context['exception'] ?? '', 'SMTP Connection timed out');
            });

        // Trigger task assignment notification
        $this->notificationService->notifyTaskAssigned(
            $this->task,
            [$this->assignee->id],
            $this->owner
        );

        // In-app notification MUST be safely persisted in custom_notifications
        $notification = NotificationModel::where('user_id', $this->assignee->id)->first();
        $this->assertNotNull($notification, 'In-app notification must persist even when SMTP transport fails');
        $this->assertEquals("Task Assigned: {$this->task->title}", $notification->title);
        $this->assertEquals('pm.task_assigned', $notification->data['type']);
    }

    #[Test]
    public function dispatch_time_project_access_revocation_suppresses_email_under_option_b(): void
    {
        // Revoke assignee from department and project
        $deptService = app(DepartmentMembershipService::class);
        $deptService->removeMember($this->department, $this->assignee);
        PmProjectMember::where('project_id', $this->project->id)->where('user_id', $this->assignee->id)->delete();

        // MailChannel should NEVER be called for an unauthorized user
        $mockMailChannel = $this->createMock(MailChannel::class);
        $mockMailChannel->expects($this->never())->method('send');
        $this->app->instance(MailChannel::class, $mockMailChannel);

        // Attempt notification
        $this->notificationService->notifyTaskAssigned(
            $this->task,
            [$this->assignee->id],
            $this->owner
        );

        // Zero notifications created
        $count = NotificationModel::where('user_id', $this->assignee->id)->count();
        $this->assertEquals(0, $count, 'Revoked user must not receive in-app or mail notifications');
    }

    #[Test]
    public function user_with_email_disabled_receives_in_app_notification_without_mail_channel(): void
    {
        // Set user preferences with email disabled
        NotificationPreference::updateOrCreate(
            ['user_id' => $this->assignee->id],
            [
                'email_enabled' => false,
                'in_app_enabled' => true,
            ]
        );
        $this->assignee->unsetRelation('notificationPreferences');

        $mockMailChannel = $this->createMock(MailChannel::class);
        $mockMailChannel->expects($this->never())->method('send');
        $this->app->instance(MailChannel::class, $mockMailChannel);

        $this->notificationService->notifyTaskAssigned(
            $this->task,
            [$this->assignee->id],
            $this->owner
        );

        // In-app notification must still be generated
        $notification = NotificationModel::where('user_id', $this->assignee->id)->first();
        $this->assertNotNull($notification);
        $this->assertEquals('pm.task_assigned', $notification->data['type']);
    }

    #[Test]
    public function safe_dispatch_revalidates_option_b_access_before_channel_dispatch(): void
    {
        $notification = new TaskAssignedNotification(
            $this->task,
            $this->project,
            $this->owner
        );

        // When user has access: via contains in-app and mail
        $channels = $notification->via($this->assignee);
        $this->assertContains(\App\Services\Notifications\CustomDatabaseChannel::class, $channels);
        $this->assertContains('mail', $channels);

        // When access is revoked: via returns empty array []
        $deptService = app(DepartmentMembershipService::class);
        $deptService->removeMember($this->department, $this->assignee);
        PmProjectMember::where('project_id', $this->project->id)->where('user_id', $this->assignee->id)->delete();

        $revokedChannels = $notification->via($this->assignee);
        $this->assertEmpty($revokedChannels, 'via() must return empty array when recipient has no project access');
    }
}
