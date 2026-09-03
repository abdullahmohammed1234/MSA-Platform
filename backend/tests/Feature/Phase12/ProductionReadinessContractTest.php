<?php

namespace Tests\Feature\Phase12;

use App\Ems\Support\EmsPermissions;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Notifications\Auth\ResetPasswordNotification;
use App\Notifications\Auth\VerifyEmailNotification;
use Database\Seeders\Ems\EmsRolePermissionSeeder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Phase 12 — Production readiness & operational closure contracts.
 */
class ProductionReadinessContractTest extends TestCase
{
    use RefreshDatabase;

    private User $cmsUser;

    private User $damsUser;

    private User $learner;

    private User $emsUser;

    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(
            ['slug' => 'super-admin'],
            ['name' => 'Super Admin', 'uuid' => (string) Str::uuid()]
        );
        $this->seed(EmsRolePermissionSeeder::class);

        $manageHomepage = $this->perm('manage_homepage', 'Website');
        $manageMedia = $this->perm('manage_media', 'Website');
        $manageCourses = $this->perm('manage_courses', 'Academy');

        $cmsRole = $this->role('cms-editor', 'CMS Editor');
        $cmsRole->permissions()->attach([$manageHomepage->id, $manageMedia->id]);

        $damsRole = $this->role('dams-operator', 'DAMS Operator');
        $damsRole->permissions()->attach($manageCourses);

        $this->cmsUser = $this->user('cms@phase12.test', 'CMS', $cmsRole);
        $this->damsUser = $this->user('dams@phase12.test', 'DAMS', $damsRole);
        $this->learner = $this->user('learner@phase12.test', 'Learner', $this->role('volunteer', 'Volunteer'));
        $this->adminUser = $this->user('admin@phase12.test', 'Admin', $this->role('admin', 'Admin'));

        $emsRole = Role::where('slug', 'event-administrator')->first()
            ?? Role::where('slug', 'ems-event-administrator')->first();
        $this->assertNotNull($emsRole);
        $this->emsUser = $this->user('ems@phase12.test', 'EMS', $emsRole);

        // Grant explicit application access for testing independent gates
        $appAccessService = app(\App\Services\ApplicationAccessService::class);
        $appAccessService->grant($this->cmsUser, 'cms');
        $appAccessService->grant($this->damsUser, 'dams');
        $appAccessService->grant($this->emsUser, 'ems');
    }

    private function perm(string $slug, string $module): Permission
    {
        return Permission::firstOrCreate(
            ['slug' => $slug],
            [
                'uuid' => (string) Str::uuid(),
                'name' => $slug,
                'module' => $module,
                'description' => $slug,
            ]
        );
    }

    private function role(string $slug, string $name): Role
    {
        return Role::firstOrCreate(
            ['slug' => $slug],
            ['uuid' => (string) Str::uuid(), 'name' => $name, 'description' => $name]
        );
    }

    private function user(string $email, string $name, Role $role): User
    {
        $user = User::factory()->create([
            'email' => $email,
            'name' => $name,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $user->roles()->attach($role);

        return $user;
    }

    /** @test */
    public function production_env_example_documents_required_safety_settings(): void
    {
        $example = File::get(base_path('.env.example'));
        foreach ([
            'APP_DEBUG=false',
            'QUEUE_CONNECTION=database',
            'FORCE_HTTPS',
            'TRUSTED_PROXIES',
            'SESSION_SECURE_COOKIE',
            'EMS_PAYMENTS_QUEUE',
        ] as $needle) {
            $this->assertStringContainsString($needle, $example);
        }
        $this->assertStringNotContainsString('migrate:fresh', $example);
    }

    /** @test */
    public function prepare_production_scripts_forbid_destructive_migrate_and_sync_queue(): void
    {
        $sh = File::get(base_path('scripts/prepare-production.sh'));
        $ps1 = File::get(base_path('scripts/prepare-production.ps1'));

        $this->assertStringContainsString('migrate --force', $sh);
        $this->assertStringContainsString('migrate --force', $ps1);
        $this->assertStringContainsString('migrate:fresh', $sh);
        $this->assertStringContainsString('NEVER', $sh);
        $this->assertStringContainsString('QUEUE_CONNECTION=sync', $sh);
        $this->assertStringContainsString('msa:production-check', $sh);
        $this->assertStringContainsString('msa:production-check', $ps1);
    }

    /** @test */
    public function production_check_command_runs_without_exposing_secrets(): void
    {
        $exit = Artisan::call('msa:production-check');
        $output = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringNotContainsString('SQUARE_ACCESS_TOKEN', $output);
        $this->assertStringNotContainsString('MAIL_PASSWORD', $output);
        $this->assertStringContainsString('production readiness check', strtolower($output));
    }

    /** @test */
    public function scheduler_registers_critical_ems_and_platform_tasks(): void
    {
        $bootstrap = File::get(base_path('bootstrap/app.php'));
        foreach ([
            'ExpireAbandonedCheckoutsJob',
            'ProcessDueRemindersJob',
            'ProcessDueNotificationsJob',
            'ReconcileSquareSalesJob',
            'AggregateAnalyticsMetricsJob',
        ] as $job) {
            $this->assertStringContainsString($job, $bootstrap);
        }

        Artisan::call('schedule:list');
        $list = Artisan::output();
        $this->assertTrue(
            str_contains($list, 'ExpireAbandonedCheckouts')
            || str_contains($list, 'abandoned')
            || str_contains($bootstrap, 'ExpireAbandonedCheckoutsJob')
        );
    }

    /** @test */
    public function queue_configuration_names_required_partitions(): void
    {
        // phpunit.xml forces QUEUE_CONNECTION=sync; production default remains database in config/queue.php.
        $queueConfig = File::get(base_path('config/queue.php'));
        $this->assertStringContainsString("env('QUEUE_CONNECTION', 'database')", $queueConfig);
        $this->assertSame('ems-payments', config('ems.payments.queue'));
        $this->assertSame('ems-operations', config('ems.operations.queue'));
        $this->assertSame('ems-notifications', config('ems.notifications.queue'));
        $this->assertTrue(Schema::hasTable('jobs'));
        $this->assertTrue(Schema::hasTable('failed_jobs'));
    }

    /** @test */
    public function auth_and_boundary_invariants_hold(): void
    {
        $token = $this->cmsUser->createToken('p12')->plainTextToken;
        $this->withHeaders(['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'])
            ->getJson('/api/v1/auth/me')
            ->assertOk();

        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
        $this->getJson('/api/v1/auth/me')->assertStatus(401);

        $this->actingAs($this->cmsUser, 'sanctum')->getJson(route('api.admin.cms.homepage.index'))->assertOk();
        $this->actingAs($this->cmsUser, 'sanctum')->getJson(route('api.admin.courses.index'))->assertStatus(403);

        $this->actingAs($this->damsUser, 'sanctum')->getJson(route('api.admin.courses.index'))->assertOk();
        $this->actingAs($this->damsUser, 'sanctum')->getJson(route('api.admin.cms.homepage.index'))->assertStatus(403);

        $this->actingAs($this->learner, 'sanctum')->getJson('/api/v1/academy/courses')->assertOk();
        $this->actingAs($this->learner, 'sanctum')->getJson(route('api.admin.courses.index'))->assertStatus(403);

        $this->assertTrue($this->emsUser->hasPermission(EmsPermissions::EVENTS_VIEW));
        $this->assertFalse($this->emsUser->hasPermission('manage_homepage'));

        $emsToken = $this->emsUser->createToken('p12')->plainTextToken;
        $this->flushHeaders();
        $this->app['auth']->forgetGuards();
        $this->withHeaders(['Authorization' => 'Bearer '.$emsToken, 'Accept' => 'application/json'])
            ->getJson('/api/v1/ems/events')
            ->assertOk();

        $this->flushHeaders();
        $this->app['auth']->forgetGuards();
        $this->actingAs($this->emsUser, 'sanctum')->getJson(route('api.admin.cms.homepage.index'))->assertStatus(403);

        $this->assertTrue($this->adminUser->hasRole('admin'));
        $this->assertTrue($this->adminUser->hasPermission('manage_homepage'));
        $this->actingAs($this->adminUser, 'sanctum')->getJson(route('api.admin.cms.homepage.index'))->assertOk();
    }

    /** @test */
    public function auth_notifications_remain_queued_and_cpanel_is_sync_only(): void
    {
        $this->assertTrue(is_subclass_of(VerifyEmailNotification::class, ShouldQueue::class));
        $this->assertTrue(is_subclass_of(ResetPasswordNotification::class, ShouldQueue::class));

        $cpanel = File::get(base_path('../.cpanel.yml'));
        $this->assertStringContainsString('rsync', $cpanel);
        $this->assertStringContainsString('PRODUCTION_OPERATIONS_CHECKLIST', $cpanel);
        $this->assertStringContainsString('cPanel sync only', $cpanel);

        $executableTasks = collect(preg_split('/\R/', $cpanel))
            ->filter(fn (string $line) => (bool) preg_match('/^\s*-\s+\//', $line));
        $this->assertNotEmpty($executableTasks);
        $hasRsync = false;
        foreach ($executableTasks as $line) {
            $this->assertStringNotContainsString('artisan', $line);
            $this->assertStringNotContainsString('migrate', $line);
            if (str_contains($line, 'rsync')) {
                $hasRsync = true;
            }
        }
        $this->assertTrue($hasRsync, 'At least one executable task in .cpanel.yml must execute rsync');
    }

    /** @test */
    public function systems_email_probe_does_not_claim_delivery_for_log_mailer_in_production(): void
    {
        $this->app['env'] = 'production';
        config(['mail.default' => 'log']);

        $systemView = $this->perm('system.view', 'System');
        $role = $this->role('sys-viewer', 'Sys Viewer');
        $role->permissions()->sync([$systemView->id]);
        $viewer = $this->user('sys@phase12.test', 'Sys', $role);
        app(\App\Services\ApplicationAccessService::class)->grant($viewer, 'admin-portal');

        $services = $this->actingAs($viewer)
            ->getJson('/api/v1/admin/systems')
            ->assertOk()
            ->json('platform_services');

        $email = collect($services)->firstWhere('id', 'email');
        $this->assertNotNull($email);
        $reason = strtolower((string) ($email['status_reason'] ?? $email['message'] ?? ''));
        $this->assertSame('degraded', $email['status']);
        $this->assertStringContainsString('will not deliver', $reason);
    }

    /** @test */
    public function systems_queue_probe_marks_sync_connection_degraded_in_production(): void
    {
        $this->app['env'] = 'production';
        config(['queue.default' => 'sync']);

        $systemView = $this->perm('system.view', 'System');
        $role = $this->role('sys-viewer-2', 'Sys Viewer 2');
        $role->permissions()->sync([$systemView->id]);
        $viewer = $this->user('sys2@phase12.test', 'Sys2', $role);
        app(\App\Services\ApplicationAccessService::class)->grant($viewer, 'admin-portal');

        $services = $this->actingAs($viewer)
            ->getJson('/api/v1/admin/systems')
            ->assertOk()
            ->json('platform_services');

        $queues = collect($services)->firstWhere('id', 'queues');
        $this->assertNotNull($queues);
        $this->assertSame('degraded', $queues['status']);
    }

    /** @test */
    public function systems_registry_and_incidents_contract(): void
    {
        $ids = array_keys(config('systems.applications', []));
        sort($ids);
        $this->assertSame(['cms', 'dams', 'dawah-academy', 'donations', 'ems', 'main-website', 'sponsorship', 'store'], $ids);
        $this->assertArrayHasKey('queues', config('systems.platform_services'));
        $this->assertArrayHasKey('database', config('systems.platform_services'));
        $this->assertArrayHasKey('email', config('systems.platform_services'));
        $this->assertArrayHasKey('storage', config('systems.platform_services'));
    }

    /** @test */
    public function storage_and_legacy_events_invariants(): void
    {
        Storage::fake('public');
        $file = UploadedFile::fake()->create('a.jpg', 20, 'image/jpeg');

        $this->actingAs($this->damsUser)
            ->postJson(route('api.admin.academy.assets.upload'), ['file' => $file])
            ->assertStatus(201)
            ->assertJsonPath('owner', 'academy');
        $this->assertDatabaseCount('media', 0);

        $this->actingAs($this->cmsUser)
            ->postJson('/api/v1/admin/cms/media', ['file' => $file])
            ->assertStatus(201);
        $this->assertDatabaseCount('media', 1);

        $this->getJson('/api/v1/ems/public/events')->assertOk();
        $this->getJson('/api/v1/website/events')->assertStatus(410);
        $this->assertTrue(Schema::hasTable('events'));
        $this->assertFalse((bool) config('cms.legacy_events.drop_schema'));
    }
}
