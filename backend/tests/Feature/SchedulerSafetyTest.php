<?php

namespace Tests\Feature;

use App\Ems\Jobs\ProcessDueNotificationsJob;
use App\Ems\Jobs\ProcessDueRemindersJob;
use App\Ems\Models\EventNotification;
use App\Ems\Services\Notifications\QueuedEventNotificationDispatcher;
use App\Ems\Services\Notifications\ReminderService;
use App\Jobs\Maintenance\DatabaseMaintenanceJob;
use App\Mail\DailyVolunteerDigestMail;
use App\Mlibms\Mail\LoanDueDateReminderMail;
use App\Models\VolunteerRegistration;
use App\Volunteering\Models\Opportunity;
use App\Volunteering\Models\Shift;
use App\Volunteering\Models\Signup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SchedulerSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_volunteer_digest_does_not_send_when_disabled(): void
    {
        Mail::fake();
        config(['features.volunteer_digest_enabled' => false]);

        VolunteerRegistration::factory()->create([
            'email' => 'today@sfu.ca',
            'created_at' => now(),
        ]);

        $this->artisan('volunteer:send-daily-digest')->assertExitCode(0);

        Mail::assertNothingSent();
        Mail::assertNothingQueued();
    }

    public function test_library_overdue_command_does_not_queue_mail_when_disabled(): void
    {
        Mail::fake();
        config(['features.mlibms_overdue_processing_enabled' => false]);

        $this->artisan('mlibms:process-overdue-and-reminders')->assertExitCode(0);

        Mail::assertNothingQueued();
        Mail::assertNotQueued(LoanDueDateReminderMail::class);
    }

    public function test_vms_reminders_do_not_create_notifications_when_disabled(): void
    {
        config(['vms.notifications.reminders.enabled' => false]);

        $opportunity = Opportunity::create([
            'title' => 'Friday Prayer Ushering',
            'slug' => 'friday-prayer-ushering-safety',
            'status' => 'open',
            'start_at' => now()->addHours(20),
        ]);

        $shift = Shift::create([
            'opportunity_id' => $opportunity->id,
            'name' => 'Main Shift',
            'start_at' => now()->addHours(20),
            'end_at' => now()->addHours(22),
            'status' => 'open',
        ]);

        Signup::create([
            'opportunity_id' => $opportunity->id,
            'shift_id' => $shift->id,
            'name' => 'Usher One',
            'email' => 'usher-safety@sfu.ca',
            'status' => 'signed_up',
        ]);

        Artisan::call('vms:send-reminders');

        $this->assertSame(0, EventNotification::query()->where('type', 'vms_shift_reminder')->count());
    }

    public function test_database_optimize_does_not_touch_the_database_when_disabled(): void
    {
        config(['features.database_optimize_enabled' => false]);

        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });

        (new DatabaseMaintenanceJob())->handle();

        $this->assertSame(0, $queries);
    }

    public function test_ems_reminder_job_does_not_run_when_notifications_are_disabled(): void
    {
        config(['ems.notifications.enabled' => false]);

        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });

        (new ProcessDueRemindersJob())->handle($this->app->make(ReminderService::class));
        (new ProcessDueNotificationsJob())->handle($this->app->make(QueuedEventNotificationDispatcher::class));

        $this->assertSame(0, $queries);
    }

    public function test_disabled_digest_does_not_use_the_digest_mailable(): void
    {
        Mail::fake();
        config(['features.volunteer_digest_enabled' => false]);

        $this->artisan('volunteer:send-daily-digest')->assertExitCode(0);

        Mail::assertNotSent(DailyVolunteerDigestMail::class);
    }
}
