<?php
# d:\projects\msa + dawah\MSA Platform\backend\tests\Unit\Services\QueueManagementServiceTest.php

namespace Tests\Unit\Services;

use App\Services\Queue\QueueManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class QueueManagementServiceTest extends TestCase
{
    use RefreshDatabase;

    protected QueueManagementService $queueService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->queueService = new QueueManagementService();
    }

    public function test_get_queue_status_returns_counts()
    {
        DB::table('jobs')->insert([
            'queue' => 'high',
            'payload' => '{}',
            'attempts' => 0,
            'available_at' => now()->timestamp,
            'created_at' => now()->timestamp,
        ]);

        DB::table('failed_jobs')->insert([
            'uuid' => 'test-uuid-123',
            'connection' => 'database',
            'queue' => 'default',
            'payload' => json_encode(['displayName' => 'TestJob']),
            'exception' => 'SomeException',
            'failed_at' => now(),
        ]);

        $status = $this->queueService->getQueueStatus();

        $highQueue = collect($status)->firstWhere('name', 'high');
        $this->assertEquals(1, $highQueue['pending_count']);

        $defaultQueue = collect($status)->firstWhere('name', 'default');
        $this->assertEquals(1, $defaultQueue['failed_count']);
    }

    public function test_get_failed_jobs_paginates_and_maps()
    {
        DB::table('failed_jobs')->insert([
            'uuid' => 'test-uuid-123',
            'connection' => 'database',
            'queue' => 'default',
            'payload' => json_encode(['displayName' => 'TestJob']),
            'exception' => 'SomeException',
            'failed_at' => now(),
        ]);

        $failed = $this->queueService->getFailedJobs(5);

        $this->assertEquals(1, $failed['total']);
        $this->assertEquals('TestJob', $failed['data'][0]['name']);
    }

    public function test_delete_failed_job_removes_from_db()
    {
        DB::table('failed_jobs')->insert([
            'uuid' => 'test-uuid-delete',
            'connection' => 'database',
            'queue' => 'default',
            'payload' => '{}',
            'exception' => 'Exception',
            'failed_at' => now(),
        ]);

        $deleted = $this->queueService->deleteFailedJob('test-uuid-delete');

        $this->assertTrue($deleted);
        $this->assertEquals(0, DB::table('failed_jobs')->count());
    }

    public function test_clear_queue_deletes_pending_jobs()
    {
        DB::table('jobs')->insert([
            'queue' => 'low',
            'payload' => '{}',
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->timestamp,
            'created_at' => now()->timestamp,
        ]);

        $cleared = $this->queueService->clearQueue('low');

        $this->assertTrue($cleared);
        $this->assertEquals(0, DB::table('jobs')->where('queue', 'low')->count());
    }
}
