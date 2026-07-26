<?php

namespace Database\Seeders\Ems;

use App\Ems\Support\EmsRoles;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Creates one throwaway account per EMS role for local development.
 *
 * Guarded three ways so it can never reach a real environment:
 *   - refuses to run when the app environment is production
 *   - only runs when EMS_SEED_DEV_USERS is explicitly enabled
 *   - generates a random password per run unless one is supplied in the
 *     environment, and prints it to the console rather than committing it
 *
 * The addresses are @example.test, a reserved TLD that cannot resolve, so
 * these accounts can never receive mail even if the seeder is run by mistake.
 */
class EmsDevelopmentUserSeeder extends Seeder
{
    /**
     * @var array<int, array{name: string, email: string, role: string}>
     */
    private const USERS = [
        ['name' => 'EMS Event Administrator', 'email' => 'ems-admin@example.test', 'role' => EmsRoles::EVENT_ADMINISTRATOR],
        ['name' => 'EMS Event Organizer', 'email' => 'ems-organizer@example.test', 'role' => EmsRoles::EVENT_ORGANIZER],
        ['name' => 'EMS Event Staff', 'email' => 'ems-staff@example.test', 'role' => EmsRoles::EVENT_STAFF],
        ['name' => 'EMS Attendee', 'email' => 'ems-attendee@example.test', 'role' => EmsRoles::ATTENDEE],
    ];

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->warn('[EMS] Refusing to seed development users in production.');

            return;
        }

        if (! config('ems.seed_development_users')) {
            $this->command?->info('[EMS] Development users skipped. Set EMS_SEED_DEV_USERS=true to create them.');

            return;
        }

        $password = (string) (env('EMS_DEV_USER_PASSWORD') ?: Str::password(16));

        foreach (self::USERS as $definition) {
            $role = Role::where('slug', $definition['role'])->first();

            if (! $role) {
                continue;
            }

            $user = User::firstOrCreate(
                ['email' => $definition['email']],
                [
                    'uuid' => (string) Str::uuid(),
                    'name' => $definition['name'],
                    'password' => Hash::make($password),
                    'email_verified_at' => now(),
                    'is_active' => true,
                ]
            );

            $user->roles()->syncWithoutDetaching([$role->id]);
        }

        $this->command?->info('[EMS] Seeded ' . count(self::USERS) . ' development users.');
        $this->command?->warn('[EMS] Development password for this run: ' . $password);
    }
}
