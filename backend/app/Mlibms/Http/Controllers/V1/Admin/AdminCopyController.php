<?php

namespace App\Mlibms\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Mlibms\Http\Resources\CopyResource;
use App\Mlibms\Models\Book;
use App\Mlibms\Models\Copy;
use App\Mlibms\Services\CopyService;
use App\Mlibms\Services\IntakeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminCopyController extends Controller
{
    public function __construct(
        protected CopyService $copyService,
        protected IntakeService $intakeService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Copy::with(['book.authors', 'location']);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('condition')) {
            $query->where('condition', $request->input('condition'));
        }

        if ($request->filled('search')) {
            $term = $request->input('search');
            $query->where(function ($q) use ($term) {
                $q->where('barcode', 'LIKE', "%{$term}%")
                  ->orWhere('accession_number', 'LIKE', "%{$term}%")
                  ->orWhereHas('book', function ($bq) use ($term) {
                      $bq->where('title', 'LIKE', "%{$term}%");
                  });
            });
        }

        $copies = $query->orderBy('created_at', 'desc')->paginate($request->input('per_page', 25));

        return response()->json([
            'data' => CopyResource::collection($copies),
            'meta' => [
                'current_page' => $copies->currentPage(),
                'last_page' => $copies->lastPage(),
                'per_page' => $copies->perPage(),
                'total' => $copies->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'book_id' => 'required|exists:mlibms_books,id',
            'location_id' => 'nullable|exists:mlibms_locations,id',
            'condition' => 'nullable|string|in:new,good,fair,worn,damaged',
            'acquisition_cost_cents' => 'nullable|integer|min:0',
            'replacement_cost_cents' => 'nullable|integer|min:0',
            'notes' => 'nullable|string',
        ]);

        $book = Book::findOrFail($data['book_id']);

        $copy = $this->intakeService->addCopy(
            book: $book,
            locationId: $data['location_id'] ?? null,
            condition: $data['condition'] ?? 'good',
            acquisitionCostCents: $data['acquisition_cost_cents'] ?? null,
            replacementCostCents: $data['replacement_cost_cents'] ?? null,
            notes: $data['notes'] ?? null
        );

        return response()->json([
            'message' => 'Physical copy created successfully.',
            'data' => new CopyResource($copy->load(['book', 'location'])),
        ], 201);
    }

    public function update(Request $request, string $uuid): JsonResponse
    {
        $copy = Copy::where('uuid', $uuid)->firstOrFail();

        $updated = $this->copyService->updateCopyState($copy, $request->all());

        return response()->json([
            'message' => 'Copy updated successfully.',
            'data' => new CopyResource($updated),
        ]);
    }
}
