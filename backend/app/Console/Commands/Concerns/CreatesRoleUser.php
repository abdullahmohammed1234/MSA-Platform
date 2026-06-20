<?php

namespace App\Console\Commands\Concerns;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

trait CreatesRoleUser
{
    protected function createRoleUser(
        string $roleSlug,
        string $roleLabel,
        string $email,
        string $name,
        string $password
    ): int {
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

        $role = Role::where('slug', $roleSlug)->first();
        if (!$role) {
            $this->error("{$roleLabel} role with slug \"{$roleSlug}\" not found. Please run migrations and seeders first.");

            return Command::FAILURE;
        }

        $user = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => $name,
            'email' => trim(strtolower($email)),
            'password' => Hash::make($password),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        $user->roles()->attach($role->id);

        $this->info("{$roleLabel} user successfully created!");
        $this->line("Email: {$user->email}");
        $this->line("Name: {$user->name}");
        $this->line("Role: {$roleLabel}");

        return Command::SUCCESS;
    }
}
