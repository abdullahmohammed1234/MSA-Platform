<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Permission;
use App\Models\Role;
use App\Models\JobLog;
use App\Models\Course;
use App\Jobs\Email\SendCourseCompletionEmailJob;
use App\Jobs\Certificates\GenerateCertificateJob;
use App\Jobs\Analytics\ProcessAnalyticsEventJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class QueueSystemTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles & permissions
        $viewPermission = Permission::firstOrCreate([
            'slug' => 'view_queue_status',
            'name' => 'View Queue Status',
            'module' => 'System'
        ]);
        
        $managePermission = Permission::firstOrCreate([
            'slug' => 'manage_queues',
            'name' => 'Manage Queues',
            'module' => 'System'
        ]);

        $adminRole = Role::firstOrCreate([
            'slug' => 'admin',
            'name' => 'Admin'
        ]);

        $adminRole->permissions()->sync([$viewPermission->id, $managePermission->id]);

        // Create users
        $this->admin = User::factory()->create([
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $this->admin->roles()->sync([$adminRole->id]);

        $this->user = User::factory()->create([
            'is_active' => true,
        ]);
    }

    /**
     * Test admin queue overview API requires authentication and permissions.
     */
    public function test_queue_overview_api_requires_permission(): void
    {
        // 1. Unauthenticated request should fail
        $response = $this->getJson('/api/v1/admin/system/queues');
        $response->assertStatus(401);

        // 2. User without permission should fail
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/admin/system/queues');
        $response->assertStatus(403);

        // 3. Admin with permission should succeed
        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/admin/system/queues');
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'queues',
                'metrics' => [
                    'total_jobs_24h',
                    'completed_jobs_24h',
                    'failed_jobs_24h',
                    'success_rate_24h',
                ]
            ]);
    }

    /**
     * Test job logs history API.
     */
    public function test_can_view_job_logs(): void
    {
        // Populate fake log entries
        JobLog::create([
            'job_uuid' => 'test-uuid-1',
            'job_name' => 'TestJob',
            'queue' => 'default',
            'status' => 'completed',
            'duration' => 0.125,
            'attempts' => 1,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/admin/system/jobs');

        $response->assertStatus(200)
            ->assertJsonFragment([
                'job_uuid' => 'test-uuid-1',
                'job_name' => 'TestJob',
                'status' => 'completed',
            ]);
    }

    /**
     * Test jobs are dispatched successfully to designated queues.
     */
    public function test_jobs_are_dispatched_successfully(): void
    {
        Queue::fake();

        // 1. Dispatch Course completion email job
        $course = Course::create([
            'title' => 'Test Course',
            'slug' => 'test-course',
            'description' => 'Test Course Description',
            'difficulty' => 'beginner',
            'status' => 'published',
        ]);
        SendCourseCompletionEmailJob::dispatch($this->user, $course);

        Queue::assertPushed(SendCourseCompletionEmailJob::class, function ($job) {
            return $job->queue === 'default';
        });

        // 2. Dispatch Certificate generation job
        GenerateCertificateJob::dispatch($this->user, $course);

        Queue::assertPushed(GenerateCertificateJob::class, function ($job) {
            return $job->queue === 'default';
        });

        // 3. Dispatch raw analytics event job
        ProcessAnalyticsEventJob::dispatch([
            'event_name' => 'page_view',
            'module' => 'website',
            'event_type' => 'page_view'
        ]);

        Queue::assertPushed(ProcessAnalyticsEventJob::class, function ($job) {
            return $job->queue === 'low';
        });
    }

    /**
     * Test failed jobs listing and retry/deletion API endpoints.
     */
    public function test_failed_jobs_management_apis(): void
    {
        // Mock a failed job entry in database
        $uuid = 'fake-failed-job-uuid-999';
        DB::table('failed_jobs')->insert([
            'uuid' => $uuid,
            'connection' => 'database',
            'queue' => 'default',
            'payload' => json_encode(['displayName' => 'App\Jobs\Email\SendCourseCompletionEmailJob']),
            'exception' => 'Symfony\Component\Mailer\Exception\TransportException',
            'failed_at' => now(),
        ]);

        // 1. View failed list
        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/admin/system/jobs/failed');
        
        $response->assertStatus(200)
            ->assertJsonFragment([
                'uuid' => $uuid,
                'name' => 'App\Jobs\Email\SendCourseCompletionEmailJob'
            ]);

        // 2. Delete failed job
        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/v1/admin/system/jobs/failed/{$uuid}");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('failed_jobs', ['uuid' => $uuid]);
    }
}
