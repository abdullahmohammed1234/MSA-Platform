<?php

namespace App\Mlibms\Services;

use App\Mlibms\Models\Book;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class BookService
{
    /**
     * Search and filter public book catalog.
     */
    public function searchCatalog(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Book::with(['authors', 'publisher', 'primaryCategory'])
            ->withCount(['copies as total_copies_count'])
            ->withCount(['availableCopies as available_copies_count']);

        if (!empty($filters['search'])) {
            $term = trim($filters['search']);
            $query->where(function ($q) use ($term) {
                $q->where('title', 'LIKE', "%{$term}%")
                  ->orWhere('subtitle', 'LIKE', "%{$term}%")
                  ->orWhere('isbn_13', 'LIKE', "%{$term}%")
                  ->orWhere('isbn_10', 'LIKE', "%{$term}%")
                  ->orWhereHas('authors', function ($aq) use ($term) {
                      $aq->where('name', 'LIKE', "%{$term}%");
                  });
            });
        }

        if (!empty($filters['category'])) {
            $query->whereHas('primaryCategory', function ($cq) use ($filters) {
                $cq->where('slug', $filters['category']);
            });
        }

        if (!empty($filters['language'])) {
            $query->where('language', $filters['language']);
        }

        if (isset($filters['available']) && $filters['available'] === 'true') {
            $query->has('availableCopies');
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }
}
