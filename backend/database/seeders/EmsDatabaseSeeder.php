<?php

namespace Database\Seeders;

use Database\Seeders\Ems\EmsDevelopmentUserSeeder;
use Database\Seeders\Ems\EmsEmailTemplateSeeder;
use Database\Seeders\Ems\EmsEventCategorySeeder;
use Database\Seeders\Ems\EmsRolePermissionSeeder;
use Database\Seeders\Ems\EmsEventTemplateSeeder;
use Illuminate\Database\Seeder;

/**
 * Entry point for EMS seed data.
 *
 * Run standalone with:  php artisan db:seed --class=Database\\Seeders\\EmsDatabaseSeeder
 * It is also called at the end of DatabaseSeeder so a fresh platform install
 * comes up with a working EMS.
 *
 * Roles, permissions and categories are production-safe and idempotent.
 * Development users are gated — see EmsDevelopmentUserSeeder.
 */
class EmsDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            EmsRolePermissionSeeder::class,
            EmsEventCategorySeeder::class,
            EmsEmailTemplateSeeder::class,
            EmsEventTemplateSeeder::class,
            EmsDevelopmentUserSeeder::class,
        ]);
    }
}
