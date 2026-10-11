<?php

namespace App\Services\Search;

use App\Ems\Models\Event;
use App\Models\CMS\Announcement;
use App\Models\CMS\FeaturedOpportunity;
use App\Models\CMS\Resource;
use App\Store\Models\StoreProduct;
use App\Support\CmsAssetUrl;
use App\Volunteering\Models\Opportunity;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class UnifiedSearchService
{
    /**
     * Allowed content types for unified search.
     */
    public const ALLOWED_TYPES = [
        'announcement',
        'event',
        'program',
        'resource',
        'volunteer',
        'store',
    ];

    /**
     * Perform unified platform search across all public content sources.
     *
     * @param string $query
     * @param string|array|null $typeFilter
     * @param string $sortBy
     * @param int $page
     * @param int $perPage
     * @return array
     */
    public function search(
        string $query,
        string|array|null $typeFilter = null,
        string $sortBy = 'relevance',
        int $page = 1,
        int $perPage = 10
    ): array {
        $rawQuery = trim($query);

        if (mb_strlen($rawQuery) < 2) {
            return [
                'query' => $rawQuery,
                'filters' => [
                    'content_type' => $this->normalizeTypeFilter($typeFilter),
                    'sort_by' => $sortBy,
                ],
                'pagination' => [
                    'total' => 0,
                    'per_page' => $perPage,
                    'current_page' => $page,
                    'last_page' => 1,
                ],
                'type_counts' => [
                    'all' => 0,
                    'announcement' => 0,
                    'event' => 0,
                    'program' => 0,
                    'resource' => 0,
                    'volunteer' => 0,
                    'store' => 0,
                ],
                'items' => [],
                'message' => 'Search query must be at least 2 characters long.',
            ];
        }

        // Limit query length to prevent excessive execution
        $sanitizedQuery = mb_substr($rawQuery, 0, 255);
        $activeTypes = $this->resolveActiveTypes($typeFilter);

        $results = new Collection();
        $typeCounts = [
            'all' => 0,
            'announcement' => 0,
            'event' => 0,
            'program' => 0,
            'resource' => 0,
            'volunteer' => 0,
            'store' => 0,
        ];

        // 1. Search Published Announcements
        if (in_array('announcement', $activeTypes, true) || $typeFilter === null || $typeFilter === 'all') {
            $announcements = $this->searchAnnouncements($sanitizedQuery);
            $typeCounts['announcement'] = $announcements->count();
            if (in_array('announcement', $activeTypes, true)) {
                $results = $results->concat($announcements);
            }
        }

        // 2. Search Public EMS Events
        if (in_array('event', $activeTypes, true) || $typeFilter === null || $typeFilter === 'all') {
            $events = $this->searchEvents($sanitizedQuery);
            $typeCounts['event'] = $events->count();
            if (in_array('event', $activeTypes, true)) {
                $results = $results->concat($events);
            }
        }

        // 3. Search Published Programs / Featured Opportunities
        if (in_array('program', $activeTypes, true) || $typeFilter === null || $typeFilter === 'all') {
            $programs = $this->searchPrograms($sanitizedQuery);
            $typeCounts['program'] = $programs->count();
            if (in_array('program', $activeTypes, true)) {
                $results = $results->concat($programs);
            }
        }

        // 4. Search Published CMS Resources
        if (in_array('resource', $activeTypes, true) || $typeFilter === null || $typeFilter === 'all') {
            $resources = $this->searchResources($sanitizedQuery);
            $typeCounts['resource'] = $resources->count();
            if (in_array('resource', $activeTypes, true)) {
                $results = $results->concat($resources);
            }
        }

        // 5. Search Published Volunteer Opportunities
        if (in_array('volunteer', $activeTypes, true) || $typeFilter === null || $typeFilter === 'all') {
            $volunteers = $this->searchVolunteerOpportunities($sanitizedQuery);
            $typeCounts['volunteer'] = $volunteers->count();
            if (in_array('volunteer', $activeTypes, true)) {
                $results = $results->concat($volunteers);
            }
        }

        // 6. Search Published Store Products
        if (in_array('store', $activeTypes, true) || $typeFilter === null || $typeFilter === 'all') {
            $products = $this->searchStoreProducts($sanitizedQuery);
            $typeCounts['store'] = $products->count();
            if (in_array('store', $activeTypes, true)) {
                $results = $results->concat($products);
            }
        }

        $typeCounts['all'] = array_sum([
            $typeCounts['announcement'],
            $typeCounts['event'],
            $typeCounts['program'],
            $typeCounts['resource'],
            $typeCounts['volunteer'],
            $typeCounts['store'],
        ]);

        // Sorting
        $sorted = $this->sortResults($results, $sanitizedQuery, $sortBy);

        // Pagination
        $total = $sorted->count();
        $perPage = max(1, min(50, $perPage));
        $page = max(1, $page);
        $lastPage = (int) ceil($total / $perPage);
        if ($lastPage < 1) {
            $lastPage = 1;
        }

        $pagedItems = $sorted->slice(($page - 1) * $perPage, $perPage)->values()->toArray();

        return [
            'query' => $sanitizedQuery,
            'filters' => [
                'content_type' => $this->normalizeTypeFilter($typeFilter),
                'sort_by' => $sortBy === 'date' ? 'date' : 'relevance',
            ],
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => $lastPage,
            ],
            'type_counts' => $typeCounts,
            'items' => $pagedItems,
        ];
    }

    private function applyTextSearch($builder, array $columns, string $query)
    {
        $like = "%{$query}%";
        $cleanQuery = str_replace(["'", "’", "‘", "-"], "", $query);
        $cleanLike = "%{$cleanQuery}%";

        return $builder->where(function ($q) use ($columns, $like, $cleanLike, $query, $cleanQuery) {
            foreach ($columns as $column) {
                $q->orWhere($column, 'like', $like);
                if ($cleanQuery !== $query) {
                    $q->orWhere($column, 'like', $cleanLike);
                }
                $q->orWhereRaw("REPLACE(REPLACE(REPLACE({$column}, \"'\", \"\"), \"’\", \"\"), \"‘\", \"\") LIKE ?", [$cleanLike]);
            }
        });
    }

    private function searchAnnouncements(string $query): Collection
    {
        $builder = Announcement::query()
            ->where('status', 'published')
            ->where(function ($q) {
                $q->whereNull('published_at')->orWhere('published_at', '<=', now());
            });

        return $this->applyTextSearch($builder, ['title', 'summary', 'content'], $query)
            ->orderBy('published_at', 'desc')
            ->take(50)
            ->get()
            ->map(function ($item) {
                return [
                    'id' => 'announcement-' . $item->uuid,
                    'content_type' => 'announcement',
                    'type_label' => 'Announcement',
                    'title' => $item->title,
                    'excerpt' => $this->sanitizeExcerpt($item->summary ?: $item->content),
                    'destination' => "/announcements/{$item->slug}",
                    'date' => $item->published_at ? $item->published_at->format('Y-m-d') : null,
                    'category' => $this->deriveCategory($item->title, $item->summary),
                    'thumbnail' => CmsAssetUrl::resolve($item->featured_image),
                ];
            });
    }

    private function searchEvents(string $query): Collection
    {
        $builder = Event::query()
            ->whereIn('status', ['published', 'registration_open', 'registration_closed', 'live', 'completed'])
            ->where('is_public', true);

        return $this->applyTextSearch($builder, ['name', 'short_description', 'description', 'location'], $query)
            ->with('category:id,name')
            ->orderBy('start_at', 'asc')
            ->take(50)
            ->get()
            ->map(function ($item) {
                return [
                    'id' => 'event-' . $item->uuid,
                    'content_type' => 'event',
                    'type_label' => 'Event',
                    'title' => $item->name,
                    'excerpt' => $this->sanitizeExcerpt($item->short_description ?: $item->description),
                    'destination' => "/events/{$item->slug}",
                    'date' => $item->start_at ? $item->start_at->format('Y-m-d') : null,
                    'category' => $item->category?->name ?: 'Community Event',
                    'thumbnail' => CmsAssetUrl::resolve($item->banner_url),
                ];
            });
    }

    private function searchPrograms(string $query): Collection
    {
        $builder = FeaturedOpportunity::query()
            ->where('is_published', true)
            ->where(function ($q) {
                $q->whereNull('published_at')->orWhere('published_at', '<=', now());
            });

        return $this->applyTextSearch($builder, ['title', 'eyebrow', 'short_description', 'description'], $query)
            ->orderBy('sort_order', 'asc')
            ->take(50)
            ->get()
            ->map(function ($item) {
                return [
                    'id' => 'program-' . $item->uuid,
                    'content_type' => 'program',
                    'type_label' => 'Program',
                    'title' => $item->title,
                    'excerpt' => $this->sanitizeExcerpt($item->short_description ?: $item->description),
                    'destination' => '/featured-opportunities',
                    'date' => $item->published_at ? $item->published_at->format('Y-m-d') : null,
                    'category' => $item->eyebrow ?: 'Learning & Opportunities',
                    'thumbnail' => CmsAssetUrl::resolve($item->featured_image),
                ];
            });
    }

    private function searchResources(string $query): Collection
    {
        $builder = Resource::query()
            ->whereIn('status', ['published', 'active']);

        return $this->applyTextSearch($builder, ['title', 'description', 'category'], $query)
            ->take(50)
            ->get()
            ->map(function ($item) {
                $dest = ($item->is_external && !empty($item->link)) ? $item->link : '/featured-opportunities';
                return [
                    'id' => 'resource-' . $item->uuid,
                    'content_type' => 'resource',
                    'type_label' => 'Resource',
                    'title' => $item->title,
                    'excerpt' => $this->sanitizeExcerpt($item->description),
                    'destination' => $dest,
                    'date' => $item->created_at ? $item->created_at->format('Y-m-d') : null,
                    'category' => $item->category ?: 'Guide & Resources',
                    'thumbnail' => CmsAssetUrl::resolve($item->thumbnail),
                ];
            });
    }

    private function searchVolunteerOpportunities(string $query): Collection
    {
        $builder = Opportunity::query()
            ->whereIn('status', ['published', 'open', 'active']);

        return $this->applyTextSearch($builder, ['title', 'description', 'location'], $query)
            ->take(50)
            ->get()
            ->map(function ($item) {
                return [
                    'id' => 'volunteer-' . $item->uuid,
                    'content_type' => 'volunteer',
                    'type_label' => 'Volunteering',
                    'title' => $item->title,
                    'excerpt' => $this->sanitizeExcerpt($item->description),
                    'destination' => "/volunteer/{$item->slug}",
                    'date' => $item->start_at ? $item->start_at->format('Y-m-d') : null,
                    'category' => 'Volunteer Position',
                    'thumbnail' => null,
                ];
            });
    }

    private function searchStoreProducts(string $query): Collection
    {
        $builder = StoreProduct::query()
            ->where(function ($q) {
                $q->where('status', 'published')
                  ->orWhere('status', 'active');
            });

        return $this->applyTextSearch($builder, ['name', 'description'], $query)
            ->take(50)
            ->get()
            ->map(function ($item) {
                return [
                    'id' => 'store-' . $item->uuid,
                    'content_type' => 'store',
                    'type_label' => 'Store Product',
                    'title' => $item->name,
                    'excerpt' => $this->sanitizeExcerpt($item->description),
                    'destination' => "/store/product/{$item->slug}",
                    'date' => $item->created_at ? $item->created_at->format('Y-m-d') : null,
                    'category' => 'MSA Store',
                    'thumbnail' => null,
                ];
            });
    }

    private function resolveActiveTypes(string|array|null $typeFilter): array
    {
        if ($typeFilter === null || $typeFilter === '' || $typeFilter === 'all') {
            return self::ALLOWED_TYPES;
        }

        if (is_string($typeFilter)) {
            $types = array_map('trim', explode(',', $typeFilter));
        } else {
            $types = (array) $typeFilter;
        }

        $valid = array_values(array_intersect($types, self::ALLOWED_TYPES));
        return !empty($valid) ? $valid : self::ALLOWED_TYPES;
    }

    private function normalizeTypeFilter(string|array|null $typeFilter): string
    {
        if ($typeFilter === null || $typeFilter === '' || $typeFilter === 'all') {
            return 'all';
        }
        if (is_string($typeFilter)) {
            $types = array_map('trim', explode(',', $typeFilter));
            $valid = array_values(array_intersect($types, self::ALLOWED_TYPES));
            return !empty($valid) ? implode(',', $valid) : 'all';
        }
        return 'all';
    }

    private function sortResults(Collection $results, string $query, string $sortBy): Collection
    {
        if ($sortBy === 'date') {
            return $results->sort(function ($a, $b) {
                $dateA = $a['date'] ?? '1970-01-01';
                $dateB = $b['date'] ?? '1970-01-01';
                return strcmp($dateB, $dateA);
            })->values();
        }

        // Default: Relevance
        $qLower = mb_strtolower($query);

        return $results->sort(function ($a, $b) use ($qLower) {
            $scoreA = $this->calculateRelevanceScore($a, $qLower);
            $scoreB = $this->calculateRelevanceScore($b, $qLower);

            if ($scoreA !== $scoreB) {
                return $scoreB <=> $scoreA; // Higher score first
            }

            // Secondary sort: date descending
            $dateA = $a['date'] ?? '1970-01-01';
            $dateB = $b['date'] ?? '1970-01-01';
            return strcmp($dateB, $dateA);
        })->values();
    }

    private function calculateRelevanceScore(array $item, string $qLower): int
    {
        $titleLower = mb_strtolower($item['title'] ?? '');
        $excerptLower = mb_strtolower($item['excerpt'] ?? '');

        // Exact title match = 100
        if ($titleLower === $qLower) {
            return 100;
        }
        // Title starts with query = 80
        if (str_starts_with($titleLower, $qLower)) {
            return 80;
        }
        // Title contains query = 50
        if (str_contains($titleLower, $qLower)) {
            return 50;
        }
        // Excerpt contains query = 20
        if (str_contains($excerptLower, $qLower)) {
            return 20;
        }

        return 10;
    }

    private function sanitizeExcerpt(?string $text): string
    {
        if (!$text) {
            return 'View details on SFU MSA platform.';
        }
        $stripped = trim(strip_tags($text));
        $normalized = preg_replace('/\s+/', ' ', $stripped);
        return Str::limit($normalized, 160);
    }

    private function deriveCategory(string $title, ?string $summary): string
    {
        $combined = strtolower($title . ' ' . ($summary ?? ''));
        if (str_contains($combined, 'jumuah') || str_contains($combined, 'prayer') || str_contains($combined, 'khutbah')) {
            return 'Prayer';
        }
        if (str_contains($combined, 'volunteer') || str_contains($combined, 'board') || str_contains($combined, 'committee')) {
            return 'Board';
        }
        if (str_contains($combined, 'event') || str_contains($combined, 'halaqah') || str_contains($combined, 'social')) {
            return 'Events';
        }
        if (str_contains($combined, 'course') || str_contains($combined, 'study') || str_contains($combined, 'workshop')) {
            return 'Education';
        }
        return 'General';
    }
}
