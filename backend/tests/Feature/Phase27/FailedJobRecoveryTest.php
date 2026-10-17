<?php

namespace Tests\Feature\Phase27;

use App\Models\ApplicationAccess;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Exhausted jobs land in failed_jobs. Operators must be able to see them and requeue
 * them, and the API must not report a retry that did not happen.
 */
class FailedJobRecoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['queue.default' => 'database', 'queue.failed.driver' => 'database-uuids']);
    }

    private function operator(string ...$permissions): User
    {
        $role = Role::create(['name' => 'Queue Operator '.Str::random(4), 'slug' => 'queue-op-'.Str::random(6)]);
        foreach ($permissions as $slug) {
            $role->permissions()->attach(Permission::firstOrCreate(['slug' => $slug], ['name' => $slug, 'module' => 'System'])->id);
        }
        $user = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        $user->roles()->attach($role->id);
        ApplicationAccess::create(['user_id' => $user->id, 'application' => 'admin-portal']);

        return $user;
    }

    private function failedJob(): string
    {
        $uuid = (string) Str::uuid();
        DB::table('failed_jobs')->insert([
            'uuid' => $uuid,
            'connection' => 'database',
            'queue' => 'low',
            'payload' => json_encode([
                'uuid' => $uuid,
                'displayName' => \App\Jobs\Maintenance\NotificationCleanupJob::class,
                'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
                'maxTries' => 3,
                'attempts' => 3,
                'data' => [
                    'commandName' => \App\Jobs\Maintenance\NotificationCleanupJob::class,
                    'command' => serialize(new \App\Jobs\Maintenance\NotificationCleanupJob),
                ],
            ]),
            'exception' => 'RuntimeException: synthetic failure',
            'failed_at' => now(),
        ]);

        return $uuid;
    }

    public function test_operator_can_list_and_requeue_an_exhausted_job(): void
    {
        $uuid = $this->failedJob();
        Sanctum::actingAs($this->operator('retry_failed_jobs', 'view_queue_status', 'manage_queues'));

        $this->getJson(route('api.admin.system.jobs.failed'))->assertOk()->assertSee($uuid);

        $this->postJson(route('api.admin.system.jobs.failed.retry', $uuid))
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame(0, DB::table('failed_jobs')->where('uuid', $uuid)->count());
        $this->assertSame(1, DB::table('jobs')->where('queue', 'low')->count());
    }

    public function test_retrying_an_unknown_failed_job_is_not_reported_as_success(): void
    {
        Sanctum::actingAs($this->operator('retry_failed_jobs', 'manage_queues'));

        $this->postJson(route('api.admin.system.jobs.failed.retry', (string) Str::uuid()))
            ->assertNotFound()
            ->assertJsonPath('success', false);

        $this->assertSame(0, DB::table('jobs')->count());
    }

    public function test_retry_requires_queue_permission(): void
    {
        $uuid = $this->failedJob();
        Sanctum::actingAs($this->operator('view_queue_status'));

        $this->postJson(route('api.admin.system.jobs.failed.retry', $uuid))->assertForbidden();
        $this->assertSame(1, DB::table('failed_jobs')->where('uuid', $uuid)->count());
    }
}
