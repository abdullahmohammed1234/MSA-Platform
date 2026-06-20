<?php

namespace Tests\Feature\Console;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateAdminCommandsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'Admin', 'slug' => 'admin']);
        Role::create(['name' => 'Super Admin', 'slug' => 'super-admin']);
        Role::create(['name' => 'Mentor', 'slug' => 'mentor']);
        Role::create(['name' => 'Director', 'slug' => 'director']);
        Role::create(['name' => 'Dawah Coordinator', 'slug' => 'dawah-coordinator']);
    }

    public function test_can_create_admin_from_command_line(): void
    {
        $this->artisan('make:admin admin@example.com "Console Admin" password123')
            ->expectsOutput('Admin user successfully created!')
            ->assertExitCode(0);

        $this->assertDatabaseHas('users', [
            'email' => 'admin@example.com',
            'name' => 'Console Admin',
        ]);

        $user = User::where('email', 'admin@example.com')->first();
        $this->assertTrue($user->hasRole('admin'));
    }

    public function test_can_create_super_admin_from_command_line(): void
    {
        $this->artisan('make:superadmin super@example.com "Console Super" password123')
            ->expectsOutput('Super Admin user successfully created!')
            ->assertExitCode(0);

        $this->assertDatabaseHas('users', [
            'email' => 'super@example.com',
            'name' => 'Console Super',
        ]);

        $user = User::where('email', 'super@example.com')->first();
        $this->assertTrue($user->hasRole('super-admin'));
    }

    public function test_can_create_mentor_from_command_line(): void
    {
        $this->artisan('make:mentor mentor@example.com "Console Mentor" password123')
            ->expectsOutput('Mentor user successfully created!')
            ->assertExitCode(0);

        $user = User::where('email', 'mentor@example.com')->first();
        $this->assertTrue($user->hasRole('mentor'));
    }

    public function test_can_create_director_from_command_line(): void
    {
        $this->artisan('make:director director@example.com "Console Director" password123')
            ->expectsOutput('Director user successfully created!')
            ->assertExitCode(0);

        $user = User::where('email', 'director@example.com')->first();
        $this->assertTrue($user->hasRole('director'));
    }

    public function test_can_create_dawah_coordinator_from_command_line(): void
    {
        $this->artisan('make:dawah-coordinator coordinator@example.com "Console Coordinator" password123')
            ->expectsOutput('Dawah Coordinator user successfully created!')
            ->assertExitCode(0);

        $user = User::where('email', 'coordinator@example.com')->first();
        $this->assertTrue($user->hasRole('dawah-coordinator'));
    }
}
