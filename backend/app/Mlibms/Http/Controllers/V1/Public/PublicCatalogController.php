<?php

namespace App\Mlibms\Http\Controllers\V1\Public;

use App\Http\Controllers\Controller;
use App\Mlibms\Http\Resources\BookResource;
use App\Mlibms\Http\Resources\CategoryResource;
use App\Mlibms\Models\Book;
use App\Mlibms\Models\Category;
use App\Mlibms\Services\BookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicCatalogController extends Controller
{
    public function __construct(
        protected BookService $bookService
    ) {}

    /**
     * Search public catalog books.
     */
    public function index(Request $request): JsonResponse
    {
        $books = $this->bookService->searchCatalog($request->all(), $request->input('per_page', 15));

        return response()->json([
            'data' => BookResource::collection($books),
            'meta' => [
                'current_page' => $books->currentPage(),
                'last_page' => $books->lastPage(),
                'per_page' => $books->perPage(),
                'total' => $books->total(),
            ],
        ]);
    }

    /**
     * Get single book detail.
     */
    public function show(string $uuid): JsonResponse
    {
        $book = Book::where('uuid', $uuid)
            ->with(['authors', 'publisher', 'primaryCategory', 'copies.location'])
            ->withCount(['copies as total_copies_count'])
            ->withCount(['availableCopies as available_copies_count'])
            ->firstOrFail();

        return response()->json([
            'data' => new BookResource($book),
        ]);
    }

    /**
     * Get library categories.
     */
    public function categories(): JsonResponse
    {
        $categories = Category::with('children')->whereNull('parent_id')->get();

        return response()->json([
            'data' => CategoryResource::collection($categories),
        ]);
    }
}
