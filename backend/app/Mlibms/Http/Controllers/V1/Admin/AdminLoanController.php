<?php

namespace App\Mlibms\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Mlibms\Http\Resources\LoanResource;
use App\Mlibms\Models\Loan;
use App\Mlibms\Services\LoanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class AdminLoanController extends Controller
{
    public function __construct(
        protected LoanService $loanService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Loan::with(['copy.book.authors', 'member']);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('search')) {
            $term = $request->input('search');
            $query->where(function ($q) use ($term) {
                $q->whereHas('member', function ($mq) use ($term) {
                    $mq->where('name', 'LIKE', "%{$term}%")
                      ->orWhere('library_card_number', 'LIKE', "%{$term}%");
                })->orWhereHas('copy', function ($cq) use ($term) {
                    $cq->where('barcode', 'LIKE', "%{$term}%")
                      ->orWhereHas('book', function ($bq) use ($term) {
                          $bq->where('title', 'LIKE', "%{$term}%");
                      });
                });
            });
        }

        $loans = $query->orderBy('created_at', 'desc')->paginate($request->input('per_page', 25));

        return response()->json([
            'data' => LoanResource::collection($loans),
            'meta' => [
                'current_page' => $loans->currentPage(),
                'last_page' => $loans->lastPage(),
                'per_page' => $loans->perPage(),
                'total' => $loans->total(),
            ],
        ]);
    }

    /**
     * Administrative return override by staff/admin.
     */
    public function overrideReturn(Request $request): JsonResponse
    {
        $barcode = $request->input('copy_barcode');
        if (empty($barcode)) {
            return response()->json(['message' => 'Copy barcode is required.'], 422);
        }

        try {
            $loan = $this->loanService->staffReturnOverride(
                copyBarcode: $barcode,
                staffUser: $request->user()
            );

            return response()->json([
                'message' => 'Administrative return successfully executed.',
                'data' => new LoanResource($loan),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
