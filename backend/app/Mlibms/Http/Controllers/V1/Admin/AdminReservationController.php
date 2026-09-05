<?php

namespace App\Mlibms\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Mlibms\Http\Resources\ReservationResource;
use App\Mlibms\Models\Reservation;
use App\Mlibms\Services\ReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class AdminReservationController extends Controller
{
    public function __construct(
        protected ReservationService $reservationService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Reservation::with(['book.authors', 'copy', 'member']);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $reservations = $query->orderBy('created_at', 'desc')->paginate($request->input('per_page', 25));

        return response()->json([
            'data' => ReservationResource::collection($reservations),
            'meta' => [
                'current_page' => $reservations->currentPage(),
                'last_page' => $reservations->lastPage(),
                'per_page' => $reservations->perPage(),
                'total' => $reservations->total(),
            ],
        ]);
    }

    public function cancel(Request $request, string $uuid): JsonResponse
    {
        $reservation = Reservation::where('uuid', $uuid)->firstOrFail();

        try {
            $cancelled = $this->reservationService->cancelHold($reservation, $request->user());

            return response()->json([
                'message' => 'Reservation cancelled by staff.',
                'data' => new ReservationResource($cancelled),
            ]);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
