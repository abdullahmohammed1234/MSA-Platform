<?php
# d:\projects\msa + dawah\MSA Platform\backend\tests\Feature\Queue\QueueExecutionTest.php

namespace Tests\Feature\Queue;

use App\Models\User;
use App\Models\Course;
use App\Jobs\Email\SendCourseCompletionEmailJob;
use App\Notifications\CourseCompletedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class QueueExecutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_course_completion_email_job_executes_successfully()
    {
        Notification::fake();

        $user = User::factory()->create();
        $course = Course::factory()->create();

        $job = new SendCourseCompletionEmailJob($user, $course);
        $job->handle();

        Notification::assertSentTo(
            $user,
            CourseCompletedNotification::class,
            function ($notification, $channels) use ($course) {
                return true; // Sent successfully
            }
        );
    }
}
