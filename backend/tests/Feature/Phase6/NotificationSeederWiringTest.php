<?php

namespace Tests\Feature\Phase6;

use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\NotificationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationSeederWiringTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function notification_permissions_are_created_by_notification_seeder(): void
    {
        Role::firstOrCreate(
            ['slug' => 'super-admin'],
            ['name' => 'Super Admin', 'uuid' => (string) Str::uuid()]
        );
        Role::firstOrCreate(
            ['slug' => 'admin'],
            ['name' => 'Admin', 'uuid' => (string) Str::uuid()]
        );
        Role::firstOrCreate(
            ['slug' => 'dawah-coordinator'],
            ['name' => 'Dawah Coordinator', 'uuid' => (string) Str::uuid()]
        );

        $this->seed(NotificationSeeder::class);

        foreach ([
            'send_notifications',
            'manage_notifications',
            'manage_notification_templates',
        ] as $slug) {
            $this->assertDatabaseHas('permissions', ['slug' => $slug]);
        }

        $admin = Role::where('slug', 'admin')->firstOrFail();
        $this->assertTrue($admin->permissions()->where('slug', 'manage_notifications')->exists());
    }

    /** @test */
    public function database_seeder_includes_manage_students_and_notification_permissions(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseHas('permissions', ['slug' => 'manage_students']);
        $this->assertDatabaseHas('permissions', ['slug' => 'manage_notifications']);
        $this->assertDatabaseHas('permissions', ['slug' => 'send_notifications']);

        $coordinator = Role::where('slug', 'dawah-coordinator')->firstOrFail();
        $this->assertTrue($coordinator->permissions()->where('slug', 'manage_students')->exists());
        $this->assertTrue($coordinator->permissions()->where('slug', 'manage_notifications')->exists());
    }
}
