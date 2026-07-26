<?php

namespace Database\Seeders\Ems;

use App\Ems\Models\EventCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds the starting MSA event taxonomy.
 *
 * These are defaults, not fixtures: categories are fully editable through the
 * API, and nothing in the frontend depends on any particular slug existing.
 *
 * Safe to run in production.
 */
class EmsEventCategorySeeder extends Seeder
{
    /**
     * @var array<int, array{name: string, description: string, color: string}>
     */
    private const CATEGORIES = [
        ['name' => 'Brothers', 'description' => 'Brothers-only programming and halaqas.', 'color' => '#640c0e'],
        ['name' => 'Sisters', 'description' => 'Sisters-only programming and halaqas.', 'color' => '#b02e32'],
        ['name' => 'Community', 'description' => 'Open community gatherings and socials.', 'color' => '#8a5a2b'],
        ['name' => 'Education', 'description' => 'Lectures, seminars, courses and workshops.', 'color' => '#1f6f5c'],
        ['name' => 'Social', 'description' => 'Informal social events and outings.', 'color' => '#2f5d8c'],
        ['name' => 'Fundraising', 'description' => 'Charity drives, dinners and fundraisers.', 'color' => '#a8781a'],
        ['name' => 'Ramadan', 'description' => 'Iftars, taraweeh and Ramadan programming.', 'color' => '#4a2f7a'],
        ['name' => 'Jummah', 'description' => 'Friday prayers and khutbah programming.', 'color' => '#2c6e49'],
        ['name' => 'Other', 'description' => 'Anything that does not fit the categories above.', 'color' => '#6b7280'],
    ];

    public function run(): void
    {
        foreach (self::CATEGORIES as $index => $category) {
            EventCategory::firstOrCreate(
                ['slug' => Str::slug($category['name'])],
                [
                    'uuid' => (string) Str::uuid(),
                    'name' => $category['name'],
                    'description' => $category['description'],
                    'color' => $category['color'],
                    'is_active' => true,
                    'sort_order' => $index * 10,
                ]
            );
        }
    }
}
