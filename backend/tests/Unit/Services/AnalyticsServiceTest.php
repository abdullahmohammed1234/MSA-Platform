<?php
# d:\projects\msa + dawah\MSA Platform\backend\tests\Unit\Services\AnalyticsServiceTest.php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Models\AnalyticsSession;
use App\Services\Analytics\AnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AnalyticsServiceTest extends TestCase
{
    use RefreshDatabase;

    protected AnalyticsService $analyticsService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->analyticsService = new AnalyticsService();
    }

    public function test_sync_session_creates_new_session_if_none_exists()
    {
        $uuid = (string) \Str::uuid();
        $details = [
            'device' => 'Mobile',
            'browser' => 'Firefox',
            'platform' => 'Android',
            'referrer' => 'https://facebook.com',
        ];

        $session = $this->analyticsService->syncSession($uuid, null, $details);

        $this->assertInstanceOf(AnalyticsSession::class, $session);
        $this->assertEquals('Mobile', $session->device);
        $this->assertEquals('Firefox', $session->browser);
        $this->assertDatabaseHas('analytics_sessions', ['uuid' => $uuid]);
    }

    public function test_sync_session_updates_existing_session()
    {
        $uuid = (string) \Str::uuid();
        $session = AnalyticsSession::factory()->create([
            'uuid' => $uuid,
            'user_id' => null,
            'started_at' => now()->subMinutes(5),
            'duration' => 0,
        ]);

        $user = User::factory()->create();

        $updatedSession = $this->analyticsService->syncSession($uuid, $user->id, [
            'device' => 'Tablet',
        ]);

        $this->assertEquals($user->id, $updatedSession->user_id);
        $this->assertEquals('Tablet', $updatedSession->device);
        $this->assertGreaterThan(0, $updatedSession->duration);
    }

    public function test_track_dispatches_event_job()
    {
        Queue::fake();

        $this->analyticsService->track([
            'module' => 'website',
            'event_type' => 'click',
            'event_name' => 'button_click',
        ]);

        Queue::assertPushed(\App\Jobs\Analytics\TrackEventJob::class);
    }

    public function test_helper_tracking_methods_dispatch_jobs()
    {
        Queue::fake();

        $user = User::factory()->create();

        $this->analyticsService->trackPageView('/home', 'Homepage', null, $user->id);
        $this->analyticsService->trackCourseCompletion($user->id, 1);
        $this->analyticsService->trackQuizSubmission($user->id, 2, 85.0, true);
        $this->analyticsService->trackCertificateAward($user->id, 3);
        $this->analyticsService->trackEventRegistration($user->id, 4);

        Queue::assertPushed(\App\Jobs\Analytics\TrackEventJob::class, 5);
    }
}
