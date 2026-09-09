<?php

namespace App\Services\CMS;

use App\Models\CMS\FeaturedOpportunity;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class FeaturedOpportunityService
{
    protected $revisionService;

    public function __construct(RevisionService $revisionService)
    {
        $this->revisionService = $revisionService;
    }

    public function listPublic()
    {
        return FeaturedOpportunity::where('is_published', true)
            ->orderBy('sort_order', 'asc')
            ->orderBy('published_at', 'desc')
            ->get();
    }

    public function listAdmin(array $filters = [], int $perPage = 15)
    {
        $query = FeaturedOpportunity::query();

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('eyebrow', 'like', "%{$search}%")
                  ->orWhere('short_description', 'like', "%{$search}%");
            });
        }

        if (isset($filters['status']) && $filters['status'] !== '') {
            if ($filters['status'] === 'published') {
                $query->where('is_published', true);
            } elseif ($filters['status'] === 'draft') {
                $query->where('is_published', false);
            }
        }

        return $query->orderBy('sort_order', 'asc')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    public function findByUuid(string $uuid): ?FeaturedOpportunity
    {
        return FeaturedOpportunity::where('uuid', $uuid)->first();
    }

    public function findBySlug(string $slug): ?FeaturedOpportunity
    {
        return FeaturedOpportunity::where('slug', $slug)->first();
    }

    public function create(array $data, ?int $userId): FeaturedOpportunity
    {
        $data['uuid'] = (string) Str::uuid();
        
        $baseSlug = !empty($data['slug']) ? Str::slug($data['slug']) : Str::slug($data['title']);
        $data['slug'] = $this->generateUniqueSlug($baseSlug);

        $data['author_id'] = $userId;
        $data['features'] = isset($data['features']) ? array_values(array_filter($data['features'])) : [];

        // Handle published state / status backwards compatibility
        if (isset($data['status'])) {
            $data['is_published'] = ($data['status'] === 'published');
        } else {
            $data['is_published'] = (bool) ($data['is_published'] ?? false);
        }

        if ($data['is_published'] && empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        $opportunity = FeaturedOpportunity::create($data);

        // Save initial revision & log
        $this->revisionService->createRevision($opportunity, $userId);
        $this->revisionService->logAction($userId, 'create_featured_opportunity', $opportunity, "Created featured opportunity: {$opportunity->title}");

        Cache::forget('website_featured_opportunities');

        return $opportunity;
    }

    public function update(FeaturedOpportunity $opportunity, array $data, ?int $userId): FeaturedOpportunity
    {
        if (isset($data['title']) && empty($data['slug'])) {
            $baseSlug = Str::slug($data['title']);
            $data['slug'] = $this->generateUniqueSlug($baseSlug, $opportunity->id);
        } elseif (isset($data['slug'])) {
            $baseSlug = Str::slug($data['slug']);
            $data['slug'] = $this->generateUniqueSlug($baseSlug, $opportunity->id);
        }

        if (isset($data['features'])) {
            $data['features'] = array_values(array_filter($data['features']));
        }

        if (isset($data['status'])) {
            $data['is_published'] = ($data['status'] === 'published');
        }

        if (isset($data['is_published'])) {
            $isPublished = (bool) $data['is_published'];
            if ($isPublished && !$opportunity->is_published) {
                $data['published_at'] = now();
            }
        }

        $opportunity->update($data);
        $opportunity->refresh();

        $this->revisionService->createRevision($opportunity, $userId);
        $this->revisionService->logAction($userId, 'update_featured_opportunity', $opportunity, "Updated featured opportunity: {$opportunity->title}");

        Cache::forget('website_featured_opportunities');

        return $opportunity;
    }

    public function delete(FeaturedOpportunity $opportunity, ?int $userId): bool
    {
        $title = $opportunity->title;
        $deleted = $opportunity->delete();

        if ($deleted) {
            $this->revisionService->logAction($userId, 'delete_featured_opportunity', $opportunity, "Deleted featured opportunity: {$title}");
            Cache::forget('website_featured_opportunities');
        }

        return (bool) $deleted;
    }

    public function reorder(array $uuids, ?int $userId): void
    {
        foreach ($uuids as $index => $uuid) {
            FeaturedOpportunity::where('uuid', $uuid)->update(['sort_order' => $index + 1]);
        }

        Cache::forget('website_featured_opportunities');
    }

    public function getRevisions(FeaturedOpportunity $opportunity)
    {
        return $this->revisionService->getRevisions($opportunity);
    }

    public function rollback(FeaturedOpportunity $opportunity, int $version, ?int $userId): bool
    {
        $result = $this->revisionService->rollback($opportunity, $version, $userId);
        if ($result) {
            Cache::forget('website_featured_opportunities');
        }
        return $result;
    }

    protected function generateUniqueSlug(string $baseSlug, ?int $ignoreId = null): string
    {
        $slug = $baseSlug;
        $count = 1;

        while (
            FeaturedOpportunity::where('slug', $slug)
                ->when($ignoreId, fn($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = "{$baseSlug}-{$count}";
            $count++;
        }

        return $slug;
    }
}
