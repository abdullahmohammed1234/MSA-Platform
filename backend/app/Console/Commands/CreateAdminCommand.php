<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Role;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;

class CreateAdminCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:admin {email} {name} {password}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new admin user account with full access';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');
        $name = $this->argument('name');
        $password = $this->argument('password');

        // Validation
        $validator = Validator::make([
            'email' => $email,
            'name' => $name,
            'password' => $password,
        ], [
            'email' => 'required|email|unique:users,email',
            'name' => 'required|string|max:255',
            'password' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            $this->error('Validation failed:');
            foreach ($validator->errors()->all() as $error) {
                $this->line("- $error");
            }
            return Command::FAILURE;
        }

        // Retrieve Admin Role
        $adminRole = Role::where('slug', 'admin')->first();
        if (!$adminRole) {
            $this->error('Admin role with slug "admin" not found. Please run migrations and seeders first.');
            return Command::FAILURE;
        }

        // Create User
        $user = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => $name,
            'email' => trim(strtolower($email)),
            'password' => Hash::make($password),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        // Attach Admin Role
        $user->roles()->attach($adminRole->id);

        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database");

        $this->info('Admin user successfully created!');
        $this->line("Database: {$connection} ({$database})");
        $this->line("Email: {$user->email}");
        $this->line("Name: {$user->name}");
        $this->line('Role: Admin');

        return Command::SUCCESS;
    }
}
