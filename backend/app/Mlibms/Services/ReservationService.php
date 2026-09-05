<?php

namespace App\Mlibms\Services;

use App\Models\User;
use App\Mlibms\Models\Book;
use App\Mlibms\Models\Member;
use App\Mlibms\Models\Reservation;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ReservationService
{
    public function __construct(
        protected MemberService $memberService
    ) {}

    /**
     * Reserve a book for a member.
     */
    public function placeHold(Book $book, User $user): Reservation
    {
        return DB::transaction(function () use ($book, $user) {
            $member = $this->memberService->getOrProvisionMember($user);

            // Check if member already has an active hold on this book
            $existing = Reservation::where('book_id', $book->id)
                ->where('member_id', $member->id)
                ->whereIn('status', ['pending', 'ready_for_pickup'])
                ->first();

            if ($existing) {
                throw new RuntimeException("You already have an active hold reservation on this book.");
            }

            $currentMaxQueue = Reservation::where('book_id', $book->id)
                ->where('status', 'pending')
                ->max('queue_position') ?? 0;

            return Reservation::create([
                'book_id' => $book->id,
                'member_id' => $member->id,
                'status' => 'pending',
                'queue_position' => $currentMaxQueue + 1,
                'reserved_at' => now(),
            ]);
        });
    }

    /**
     * Cancel an active reservation.
     */
    public function cancelHold(Reservation $reservation, User $user): Reservation
    {
        return DB::transaction(function () use ($reservation) {
            if (!in_array($reservation->status->value, ['pending', 'ready_for_pickup'])) {
                throw new RuntimeException("This reservation cannot be cancelled because it is currently {$reservation->status->label()}.");
            }

            $bookId = $reservation->book_id;
            $oldPosition = $reservation->queue_position;

            $reservation->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ]);

            // Shift queue positions for remaining pending holds
            Reservation::where('book_id', $bookId)
                ->where('status', 'pending')
                ->where('queue_position', '>', $oldPosition)
                ->decrement('queue_position');

            // If copy was marked reserved for this hold, reassign to next pending hold or mark available
            if ($reservation->copy_id && $reservation->copy) {
                $copy = $reservation->copy;
                $nextHold = Reservation::where('book_id', $bookId)
                    ->where('status', 'pending')
                    ->orderBy('queue_position')
                    ->first();

                if ($nextHold) {
                    $nextHold->update([
                        'copy_id' => $copy->id,
                        'status' => 'ready_for_pickup',
                        'ready_at' => now(),
                        'expires_at' => now()->addDays(3),
                    ]);
                    try {
                        \Illuminate\Support\Facades\Mail::to($nextHold->member->email)->queue(new \App\Mlibms\Mail\HoldReadyForPickupMail($nextHold));
                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::error("Failed to queue hold ready mail on cancellation reassignment: " . $e->getMessage());
                    }
                } else {
                    $copy->update(['status' => 'available']);
                }
            }

            return $reservation;
        });
    }
}
