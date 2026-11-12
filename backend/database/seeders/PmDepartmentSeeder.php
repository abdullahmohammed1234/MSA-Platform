<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PmDepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $departments = [
            [
                'name' => 'Presidential',
                'slug' => 'presidential',
                'description' => 'Executive governance, institutional relations, and organizational oversight.',
                'color' => '#640c0e',
                'display_order' => 1,
            ],
            [
                'name' => 'Marketing',
                'slug' => 'marketing',
                'description' => 'Public communications, campaigns, social media, and community outreach.',
                'color' => '#b02e32',
                'display_order' => 2,
            ],
            [
                'name' => 'Finance',
                'slug' => 'finance',
                'description' => 'Financial allocations, budget management, reimbursements, and fundraising.',
                'color' => '#0f766e',
                'display_order' => 3,
            ],
            [
                'name' => 'Education',
                'slug' => 'education',
                'description' => 'Islamic literacy, halaqahs, academic workshops, and student learning resources.',
                'color' => '#1d4ed8',
                'display_order' => 4,
            ],
            [
                'name' => 'Graphics',
                'slug' => 'graphics',
                'description' => 'Creative media, brand guidelines, digital graphics, and print materials.',
                'color' => '#7c3aed',
                'display_order' => 5,
            ],
            [
                'name' => 'Events',
                'slug' => 'events',
                'description' => 'Campus gatherings, brother/sister socials, logistics, and event operations.',
                'color' => '#c2410c',
                'display_order' => 6,
            ],
            [
                'name' => 'IT',
                'slug' => 'it',
                'description' => 'Digital platform development, systems infrastructure, and technical operations.',
                'color' => '#334155',
                'display_order' => 7,
            ],
            [
                'name' => "Jumu'ah",
                'slug' => 'jumuah',
                'description' => 'Friday congregational prayer services, khateeb scheduling, campus logistics, and prayer hall management.',
                'color' => '#059669',
                'display_order' => 8,
            ],
        ];

        foreach ($departments as $dept) {
            $existing = Department::where('slug', $dept['slug'])->first();
            Department::updateOrCreate(
                ['slug' => $dept['slug']],
                [
                    'uuid' => $existing?->uuid ?? (string) Str::uuid(),
                    'name' => $dept['name'],
                    'description' => $dept['description'],
                    'color' => $dept['color'],
                    'display_order' => $dept['display_order'],
                    'is_active' => true,
                ]
            );
        }
    }
}
