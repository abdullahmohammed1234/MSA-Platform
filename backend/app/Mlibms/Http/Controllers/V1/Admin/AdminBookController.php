<?php

namespace App\Mlibms\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Mlibms\Http\Resources\BookResource;
use App\Mlibms\Models\Book;
use App\Mlibms\Services\BookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminBookController extends Controller
{
    public function __construct(
        protected BookService $bookService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $books = $this->bookService->searchCatalog($request->all(), $request->input('per_page', 20));

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

    public function store(\App\Mlibms\Http\Requests\IntakeBookRequest $request): JsonResponse
    {
        $bookData = $request->validated();
        $copiesData = $request->input('copies', []);

        $intakeService = app(\App\Mlibms\Services\IntakeService::class);
        $book = $intakeService->createBookWithCopies(
            bookPayload: $bookData,
            copiesPayload: $copiesData
        );

        return response()->json([
            'message' => 'Book created successfully.',
            'data' => new BookResource($book),
        ], 201);
    }

    public function update(Request $request, string $uuid): JsonResponse
    {
        $book = Book::where('uuid', $uuid)->firstOrFail();

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'primary_category_id' => 'nullable|exists:mlibms_categories,id',
            'publisher_id' => 'nullable|exists:mlibms_publishers,id',
            'publisher_name' => 'nullable|string|max:255',
            'isbn_10' => 'nullable|string|max:20',
            'isbn_13' => 'nullable|string|max:20',
            'edition' => 'nullable|string|max:50',
            'publication_year' => 'nullable|integer',
            'language' => 'nullable|string|max:50',
            'summary' => 'nullable|string',
            'cover_image_url' => 'nullable|url|max:500',
            'default_loan_days' => 'nullable|integer|min:1|max:365',
            'is_reference_only' => 'nullable|boolean',
            'author_names' => 'nullable|array',
            'author_names.*' => 'string|max:255',
        ]);

        if (!empty($data['publisher_name'])) {
            $publisher = \App\Mlibms\Models\Publisher::firstOrCreate(
                ['name' => trim($data['publisher_name'])],
                ['slug' => \Illuminate\Support\Str::slug($data['publisher_name'])]
            );
            $data['publisher_id'] = $publisher->id;
        }
        unset($data['publisher_name']);

        $authorNames = $data['author_names'] ?? null;
        unset($data['author_names']);

        $book->update($data);

        if ($authorNames !== null) {
            $authorIds = [];
            foreach ((array) $authorNames as $authorName) {
                if (trim($authorName) !== '') {
                    $author = \App\Mlibms\Models\Author::firstOrCreate(
                        ['name' => trim($authorName)],
                        ['slug' => \Illuminate\Support\Str::slug($authorName)]
                    );
                    $authorIds[$author->id] = ['role' => 'author'];
                }
            }
            $book->authors()->sync($authorIds);
        }

        return response()->json([
            'message' => 'Book updated successfully.',
            'data' => new BookResource($book->load(['authors', 'publisher', 'primaryCategory'])),
        ]);
    }

    public function destroy(string $uuid): JsonResponse
    {
        $book = Book::where('uuid', $uuid)->firstOrFail();
        $book->delete();

        return response()->json([
            'message' => 'Book deleted successfully.',
        ]);
    }
}
