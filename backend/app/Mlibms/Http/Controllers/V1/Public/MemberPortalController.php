<?php

namespace App\Mlibms\Http\Controllers\V1\Public;

use App\Http\Controllers\Controller;
use App\Mlibms\Http\Resources\LoanResource;
use App\Mlibms\Http\Resources\MemberResource;
use App\Mlibms\Http\Resources\ReservationResource;
use App\Mlibms\Models\Book;
use App\Mlibms\Models\Loan;
use App\Mlibms\Models\Reservation;
use App\Mlibms\Services\LoanService;
use App\Mlibms\Services\MemberService;
use App\Mlibms\Services\ReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class MemberPortalController extends Controller
{
    public function __construct(
        protected MemberService $memberService,
        protected LoanService $loanService,
        protected ReservationService $reservationService
    ) {}

    /**
     * Get member profile info & active loans.
     */
    public function me(Request $request): JsonResponse
    {
        $member = $this->memberService->getOrProvisionMember($request->user());

        $loans = Loan::where('member_id', $member->id)
            ->whereIn('status', ['active', 'overdue'])
            ->with(['copy.book.authors', 'copy.location'])
            ->orderBy('due_at', 'asc')
            ->get();

        $reservations = Reservation::where('member_id', $member->id)
            ->whereIn('status', ['pending', 'ready_for_pickup'])
            ->with(['book.authors', 'copy'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => [
                'member' => new MemberResource($member),
                'active_loans' => LoanResource::collection($loans),
                'active_holds' => ReservationResource::collection($reservations),
            ],
        ]);
    }

    /**
     * Renew an active loan.
     */
    public function renew(Request $request, string $loanUuid): JsonResponse
    {
        $member = $this->memberService->getOrProvisionMember($request->user());

        $loan = Loan::where('uuid', $loanUuid)
            ->where('member_id', $member->id)
            ->with('copy.book')
            ->firstOrFail();

        try {
            $updatedLoan = $this->loanService->renewLoan($loan, $request->user());

            return response()->json([
                'message' => 'Loan successfully renewed!',
                'data' => new LoanResource($updatedLoan),
            ]);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Place hold reservation on a book.
     */
    public function placeHold(Request $request, string $bookUuid): JsonResponse
    {
        $book = Book::where('uuid', $bookUuid)->firstOrFail();

        try {
            $reservation = $this->reservationService->placeHold($book, $request->user());

            return response()->json([
                'message' => 'Hold reservation placed successfully!',
                'data' => new ReservationResource($reservation->load('book')),
            ], 201);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Cancel an active hold reservation.
     */
    public function cancelHold(Request $request, string $reservationUuid): JsonResponse
    {
        $member = $this->memberService->getOrProvisionMember($request->user());

        $reservation = Reservation::where('uuid', $reservationUuid)
            ->where('member_id', $member->id)
            ->firstOrFail();

        try {
            $cancelled = $this->reservationService->cancelHold($reservation, $request->user());

            return response()->json([
                'message' => 'Hold reservation cancelled.',
                'data' => new ReservationResource($cancelled),
            ]);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
