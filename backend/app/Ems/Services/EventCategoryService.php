<?php

namespace App\Ems\Services;

use App\Ems\Events\EventCategoryChanged;
use App\Ems\Exceptions\ResourceInUseException;
use App\Ems\Models\EventCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

/**
 * Event category taxonomy management.
 *
 * Categories are data, not code: the frontend renders whatever this returns
 * and never carries its own list.
 */
class EventCategoryService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, EventCategory>
     */
    public function list(array $filters = []): Collection
    {
        return EventCategory::query()
            ->withCount('events')
            ->when(
                array_key_exists('is_active', $filters) && $filters['is_active'] !== null,
                fn (Builder $query) => $query->where('is_active', (bool) $filters['is_active'])
            )
            ->when(
                filled($filters['search'] ?? null),
                fn (Builder $query) => $query->where('name', 'like', '%' . $filters['search'] . '%')
            )
            ->ordered()
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): EventCategory
    {
        $category = new EventCategory();
        $category->fill($this->attributesFrom($data));
        $category->slug = $this->uniqueSlug($data['slug'] ?? $data['name']);
        $category->save();

        EventCategoryChanged::dispatch($category, 'created', $actor);

        return $category->loadCount('events');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(EventCategory $category, array $data, User $actor): EventCategory
    {
        $category->fill($this->attributesFrom($data));

        if (array_key_exists('slug', $data) && filled($data['slug']) && $data['slug'] !== $category->slug) {
            $category->slug = $this->uniqueSlug($data['slug'], $category->id);
        }

        $category->save();

        $changed = array_values(array_diff(array_keys($category->getChanges()), ['updated_at']));

        EventCategoryChanged::dispatch($category, 'updated', $actor, ['changed' => $changed]);

        return $category->loadCount('events');
    }

    /**
     * Categories in use cannot be deleted. The events FK is restrictive so the
     * invariant holds even outside the application, but checking here lets the
     * API answer with a 409 and an explanation instead of a database error.
     *
     * @throws ResourceInUseException
     */
    public function delete(EventCategory $category, User $actor): void
    {
        $inUse = $category->events()->withTrashed()->count();

        if ($inUse > 0) {
            throw new ResourceInUseException(
                sprintf(
                    'Cannot delete "%s" because %d event%s still assigned to it.',
                    $category->name,
                    $inUse,
                    $inUse === 1 ? ' is' : 's are'
                ),
                ['category' => ['Reassign or delete the events first, or deactivate the category instead.']]
            );
        }

        EventCategoryChanged::dispatch($category, 'deleted', $actor);

        $category->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributesFrom(array $data): array
    {
        return array_intersect_key($data, array_flip([
            'name',
            'description',
            'color',
            'is_active',
            'sort_order',
        ]));
    }

    private function uniqueSlug(string $source, ?int $ignoreId = null): string
    {
        $base = Str::slug($source) ?: 'category';
        $base = Str::limit($base, 120, '');
        $slug = $base;
        $suffix = 2;

        while ($this->slugTaken($slug, $ignoreId)) {
            $slug = $base . '-' . $suffix;
            $suffix++;
        }

        return $slug;
    }

    private function slugTaken(string $slug, ?int $ignoreId): bool
    {
        return EventCategory::withTrashed()
            ->where('slug', $slug)
            ->when($ignoreId !== null, fn (Builder $query) => $query->whereKeyNot($ignoreId))
            ->exists();
    }
}
