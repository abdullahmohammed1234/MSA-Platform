<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use App\Models\AnalyticsSession;
use App\Models\AnalyticsEvent;
use App\Models\AnalyticsMetric;
use App\Models\AnalyticsReport;
use App\Services\Analytics\AnalyticsService;
use App\Jobs\Analytics\TrackEventJob;
use App\Jobs\Analytics\AggregateMetricsJob;
use App\Jobs\Analytics\GenerateScheduledReportsJob;
use App\Notifications\SendAnalyticsReportNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected $adminUser;
    protected $normalUser;
    protected $analyticsService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->analyticsService = $this->app->make(AnalyticsService::class);

        // 1. Setup roles and permissions
        $adminRole = Role::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Admin',
            'slug' => 'admin',
            'description' => 'System admin',
        ]);

        $permissions = [
            'view_analytics',
            'view_reports',
            'manage_analytics',
            'export_analytics'
        ];

        foreach ($permissions as $perm) {
            $p = Permission::create([
                'uuid' => (string) Str::uuid(),
                'name' => ucfirst(str_replace('_', ' ', $perm)),
                'slug' => $perm,
                'module' => 'Analytics',
                'description' => 'Permission ' . $perm,
            ]);
            $adminRole->permissions()->attach($p);
        }

        // 2. Create users
        $this->adminUser = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
        $this->adminUser->roles()->attach($adminRole);

        $this->normalUser = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Normal User',
            'email' => 'user@example.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
    }

    /** @test */
    public function guests_and_unauthorized_users_cannot_access_analytics()
    {
        $this->getJson(route('api.analytics.overview'))->assertStatus(401);

        $this->actingAs($this->normalUser)
            ->getJson(route('api.analytics.overview'))
            ->assertStatus(403);
    }

    /** @test */
    public function authorized_users_can_access_overview_and_website_endpoints()
    {
        $this->actingAs($this->adminUser)
            ->getJson(route('api.analytics.overview'))
            ->assertStatus(200)
            ->assertJsonStructure(['success', 'kpis', 'recent_activity']);

        $this->actingAs($this->adminUser)
            ->getJson(route('api.analytics.website'))
            ->assertStatus(200)
            ->assertJsonStructure(['success', 'visitors_over_time', 'popular_pages', 'traffic_sources', 'cta_performance']);
    }

    /** @test */
    public function session_sync_creates_or_updates_a_session()
    {
        $uuid = (string) Str::uuid();

        $data = [
            'session_uuid' => $uuid,
            'device' => 'desktop',
            'browser' => 'Chrome',
            'platform' => 'Windows',
            'referrer' => 'https://google.com',
        ];

        // 1. Create session
        $this->postJson(route('api.analytics.session'), $data)
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('analytics_sessions', [
            'uuid' => $uuid,
            'device' => 'desktop',
        ]);

        // 2. Update session with user_id
        $this->actingAs($this->normalUser)
            ->postJson(route('api.analytics.session'), $data)
            ->assertStatus(200);

        $this->assertDatabaseHas('analytics_sessions', [
            'uuid' => $uuid,
            'user_id' => $this->normalUser->id,
        ]);
    }

    /** @test */
    public function tracking_service_dispatches_event_tracking_job()
    {
        Queue::fake();

        $this->analyticsService->trackPageView('/home', 'Homepage');

        Queue::assertPushed(TrackEventJob::class);
    }

    /** @test */
    public function tracking_job_creates_database_record()
    {
        $uuid = (string) Str::uuid();
        $session = AnalyticsSession::create([
            'uuid' => $uuid,
            'started_at' => now(),
        ]);

        $eventData = [
            'uuid' => (string) Str::uuid(),
            'session_uuid' => $uuid,
            'module' => 'website',
            'event_type' => 'page_view',
            'event_name' => 'page_view',
            'metadata' => ['url' => '/about'],
        ];

        $job = new TrackEventJob($eventData);
        $job->handle();

        $this->assertDatabaseHas('analytics_events', [
            'session_id' => $session->id,
            'module' => 'website',
            'event_name' => 'page_view',
        ]);
    }

    /** @test */
    public function aggregation_job_computes_daily_metrics()
    {
        $uuid = (string) Str::uuid();
        $session = AnalyticsSession::create([
            'uuid' => $uuid,
            'started_at' => now(),
        ]);

        AnalyticsEvent::create([
            'uuid' => (string) Str::uuid(),
            'session_id' => $session->id,
            'module' => 'website',
            'event_type' => 'page_view',
            'event_name' => 'page_view',
            'metadata' => ['url' => '/'],
            'occurred_at' => now(),
        ]);

        $job = new AggregateMetricsJob();
        $job->handle();

        $this->assertDatabaseHas('analytics_metrics', [
            'metric_key' => 'website_visitors_unique_daily',
            'metric_value' => 1,
        ]);
    }

    /** @test */
    public function scheduled_report_job_compiles_pdf_and_notifies_admins()
    {
        Notification::fake();

        // Seed some metric cache values to compile
        AnalyticsMetric::create([
            'metric_key' => 'website_visitors_unique_daily',
            'metric_value' => 12,
            'period' => 'daily',
            'recorded_at' => now(),
        ]);

        $job = new GenerateScheduledReportsJob('daily');
        $job->handle();

        $this->assertDatabaseHas('analytics_reports', [
            'type' => 'daily',
        ]);

        $report = AnalyticsReport::first();
        $this->assertNotNull($report->file_path);

        Notification::assertSentTo(
            $this->adminUser,
            SendAnalyticsReportNotification::class
        );
    }

    /** @test */
    public function export_endpoint_returns_csv_file_stream()
    {
        $this->actingAs($this->adminUser)
            ->getJson(route('api.analytics.export', [
                'format' => 'csv',
                'type' => 'website',
            ]))
            ->assertStatus(200)
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }
}
