<?php

namespace App\Mlibms\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Mlibms\Http\Requests\IntakeBookRequest;
use App\Mlibms\Http\Resources\BookResource;
use App\Mlibms\Services\IntakeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminIntakeController extends Controller
{
    public function __construct(
        protected IntakeService $intakeService
    ) {}

    /**
     * Scan ISBN: Check local catalog first, fallback to optional external lookup.
     */
    public function lookup(Request $request): JsonResponse
    {
        $isbn = $request->input('isbn');
        if (empty($isbn)) {
            return response()->json(['message' => 'ISBN is required.'], 422);
        }

        $existingBook = $this->intakeService->findByIsbn($isbn);
        if ($existingBook) {
            return response()->json([
                'exists_in_catalog' => true,
                'message' => 'Book already exists in local catalog.',
                'data' => new BookResource($existingBook),
            ]);
        }

        $externalData = $this->intakeService->lookupExternalMetadata($isbn);

        return response()->json([
            'exists_in_catalog' => false,
            'message' => $externalData ? 'Found metadata online.' : 'No external metadata found.',
            'suggested_data' => $externalData,
        ]);
    }

    /**
     * Intake new Book and add physical copies.
     */
    public function store(IntakeBookRequest $request): JsonResponse
    {
        $bookData = $request->validated();
        $copiesData = $request->input('copies', []);

        $book = $this->intakeService->createBookWithCopies(
            bookPayload: $bookData,
            copiesPayload: $copiesData
        );

        return response()->json([
            'message' => 'Book and physical copy inventory successfully created!',
            'data' => new BookResource($book),
        ], 201);
    }
}
