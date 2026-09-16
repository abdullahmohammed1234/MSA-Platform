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
            return response()->json([
                'status' => 'INVALID_INPUT',
                'exists_in_catalog' => false,
                'message' => 'ISBN is required.',
                'suggested_data' => null,
            ], 422);
        }

        $existingBook = $this->intakeService->findByIsbn($isbn);
        if ($existingBook) {
            return response()->json([
                'status' => 'LOCAL_EXISTS',
                'exists_in_catalog' => true,
                'message' => 'Book already exists in local catalog.',
                'data' => new BookResource($existingBook),
            ]);
        }

        $result = $this->intakeService->lookupExternalMetadataResult($isbn);

        return response()->json([
            'status' => $result['status'],
            'exists_in_catalog' => false,
            'message' => $result['message'],
            'suggested_data' => $result['data'],
            'provider' => $result['provider'] ?? null,
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
