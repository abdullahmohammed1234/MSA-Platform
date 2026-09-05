<?php

namespace App\Mlibms\Http\Controllers\V1\Public;

use App\Http\Controllers\Controller;
use App\Mlibms\Http\Requests\SelfServiceCheckoutRequest;
use App\Mlibms\Http\Requests\SelfServiceReturnRequest;
use App\Mlibms\Http\Resources\LoanResource;
use App\Mlibms\Services\LoanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class SelfServiceCirculationController extends Controller
{
    public function __construct(
        protected LoanService $loanService
    ) {}

    /**
     * Self-service checkout.
     */
    public function checkout(SelfServiceCheckoutRequest $request): JsonResponse
    {
        $user = $request->user();

        try {
            $loan = $this->loanService->selfServiceCheckout(
                copyBarcode: $request->input('copy_barcode'),
                user: $user
            );

            return response()->json([
                'message' => 'Book successfully checked out!',
                'data' => new LoanResource($loan),
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Self-service return (Loan owner only).
     */
    public function returnItem(SelfServiceReturnRequest $request): JsonResponse
    {
        $user = $request->user();

        try {
            $loan = $this->loanService->selfServiceReturn(
                copyBarcode: $request->input('copy_barcode'),
                user: $user
            );

            return response()->json([
                'message' => 'Book successfully returned!',
                'data' => new LoanResource($loan),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
