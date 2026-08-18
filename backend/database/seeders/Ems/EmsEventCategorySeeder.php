<?php

namespace Database\Seeders\Ems;

use App\Ems\Models\Event;
use App\Ems\Models\EventCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Seeds the MSA event taxonomy.
 *
 * The public events page and EMS category/template pickers are driven by this
 * list. Safe to re-run: canonical rows are upserted, and retired categories
 * are remapped then soft-deleted.
 */
class EmsEventCategorySeeder extends Seeder
{
    /**
     * @var array<int, array{name: string, slug: string, description: string, color: string}>
     */
    public const CATEGORIES = [
        [
            'name' => 'Social',
            'slug' => 'social',
            'description' => 'Socials, community gatherings, fundraisers and outings.',
            'color' => '#2f5d8c',
        ],
        [
            'name' => 'Educational/Halaqahs',
            'slug' => 'education',
            'description' => 'Lectures, halaqahs, seminars, courses and workshops.',
            'color' => '#1f6f5c',
        ],
        [
            'name' => 'Other',
            'slug' => 'other',
            'description' => 'Anything that does not fit Social or Educational/Halaqahs.',
            'color' => '#6b7280',
        ],
    ];

    /**
     * Legacy seeded slugs folded into the three canonical categories.
     *
     * @var array<string, string>
     */
    public const RETIRED_SLUG_MAP = [
        'brothers' => 'social',
        'sisters' => 'social',
        'community' => 'social',
        'fundraising' => 'social',
        'ramadan' => 'social',
        'jummah' => 'other',
    ];

    public function run(): void
    {
        self::syncCanonicalCategories();
        self::retireLegacyCategories();
    }

    /**
     * Create or refresh the three categories organizers can assign.
     */
    public static function syncCanonicalCategories(): void
    {
        foreach (self::CATEGORIES as $index => $category) {
            $row = EventCategory::withTrashed()->firstOrNew(['slug' => $category['slug']]);

            if (! $row->exists) {
                $row->uuid = (string) Str::uuid();
            }

            $row->name = $category['name'];
            $row->description = $category['description'];
            $row->color = $category['color'];
            $row->is_active = true;
            $row->sort_order = $index * 10;
            $row->save();

            if ($row->trashed()) {
                $row->restore();
            }
        }
    }

    /**
     * Rename canonical rows that already exist. Does not insert, so a fresh
     * test database is left empty.
     */
    public static function refreshExistingCanonical(): void
    {
        foreach (self::CATEGORIES as $index => $category) {
            EventCategory::query()
                ->where('slug', $category['slug'])
                ->update([
                    'name' => $category['name'],
                    'description' => $category['description'],
                    'color' => $category['color'],
                    'is_active' => true,
                    'sort_order' => $index * 10,
                ]);
        }
    }

    /**
     * Reassign events/templates off retired categories, then soft-delete them.
     *
     * No-ops when none of the canonical categories exist yet.
     */
    public static function retireLegacyCategories(): void
    {
        $canonicalSlugs = array_column(self::CATEGORIES, 'slug');
        $canonicalIds = EventCategory::query()
            ->whereIn('slug', $canonicalSlugs)
            ->pluck('id', 'slug');

        if ($canonicalIds->isEmpty()) {
            return;
        }

        $fallbackId = $canonicalIds->get('other') ?? $canonicalIds->first();

        $retired = EventCategory::query()
            ->whereNotIn('slug', $canonicalSlugs)
            ->get();

        foreach ($retired as $category) {
            $targetSlug = self::RETIRED_SLUG_MAP[$category->slug] ?? 'other';
            $targetId = $canonicalIds->get($targetSlug) ?? $fallbackId;

            if ($targetId === null || (int) $targetId === (int) $category->id) {
                continue;
            }

            DB::transaction(function () use ($category, $targetId): void {
                Event::withTrashed()
                    ->where('category_id', $category->id)
                    ->update(['category_id' => $targetId]);

                DB::table('ems_event_templates')
                    ->where('category_id', $category->id)
                    ->update(['category_id' => $targetId]);

                $category->delete();
            });
        }
    }
}
