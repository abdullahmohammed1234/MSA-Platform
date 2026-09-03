<?php

namespace App\Spms\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Spms\Models\Payment;
use App\Spms\Models\Sponsorship;
use App\Spms\Policies\SponsorshipPolicy;
use App\Spms\Services\SponsorshipPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SpmsPaymentController extends Controller
{
    public function __construct(private readonly SponsorshipPaymentService $paymentService) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Sponsorship::class);

        $query = Payment::with(['sponsorship.organization', 'commitment', 'recorder']);

        if ($request->filled('search')) {
            $term = '%' . str_replace(['%', '_'], ['\%', '\_'], trim($request->input('search'))) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('payment_number', 'like', $term)
                  ->orWhere('reference_number', 'like', $term)
                  ->orWhereHas('sponsorship.organization', fn ($oq) => $oq->where('display_name', 'like', $term));
            });
        }

        if ($request->filled('method')) {
            $query->where('payment_method', $request->input('method'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $payments = $query->orderBy('created_at', 'desc')->paginate($request->input('per_page', 25));

        return response()->json([
            'success' => true,
            'data' => $payments->items(),
            'meta' => [
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
                'per_page' => $payments->perPage(),
                'total' => $payments->total(),
            ],
        ]);
    }

    public function recordManual(Request $request, string $sponsorshipUuid): JsonResponse
    {
        Gate::authorize('managePayments', Sponsorship::class);

        $sponsorship = Sponsorship::where('uuid', $sponsorshipUuid)->firstOrFail();

        $validated = $request->validate([
            'commitment_id' => 'nullable|exists:spms_commitments,id',
            'payment_method' => 'required|string|max:50',
            'amount_cents' => 'required|integer|min:1',
            'reference_number' => 'nullable|string|max:100',
            'paid_at' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $payment = $this->paymentService->recordManualPayment($sponsorship, $validated, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Manual payment recorded successfully.',
            'data' => $payment->load(['sponsorship', 'recorder']),
        ], 201);
    }

    public function createSquareCheckout(Request $request, string $sponsorshipUuid): JsonResponse
    {
        Gate::authorize('managePayments', Sponsorship::class);

        $sponsorship = Sponsorship::where('uuid', $sponsorshipUuid)->firstOrFail();

        $validated = $request->validate([
            'amount_cents' => 'required|integer|min:100', // Minimum $1.00 CAD
            'redirect_url' => 'nullable|url',
        ]);

        try {
            $result = $this->paymentService->createSquareCheckout(
                $sponsorship,
                $validated['amount_cents'],
                $validated['redirect_url'] ?? null
            );

            return response()->json([
                'success' => true,
                'message' => 'Square checkout session created successfully.',
                'data' => $result,
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
