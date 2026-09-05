<?php

namespace App\Mlibms\Services;

use App\Models\User;
use App\Mlibms\Mail\HoldReadyForPickupMail;
use App\Mlibms\Mail\LoanCheckoutConfirmationMail;
use App\Mlibms\Models\Copy;
use App\Mlibms\Models\Loan;
use App\Mlibms\Models\Member;
use App\Mlibms\Models\Renewal;
use App\Mlibms\Models\Reservation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use RuntimeException;

class LoanService
{
    public function __construct(
        protected MemberService $memberService
    ) {}

    /**
     * Self-service checkout for an authenticated user.
     */
    public function selfServiceCheckout(string $copyBarcode, User $user): Loan
    {
        return DB::transaction(function () use ($copyBarcode, $user) {
            $copy = Copy::where('barcode', $copyBarcode)
                ->orWhere('accession_number', $copyBarcode)
                ->orWhere('uuid', $copyBarcode)
                ->lockForUpdate()
                ->first();

            if (!$copy) {
                throw new InvalidArgumentException("Physical copy with barcode '{$copyBarcode}' was not found.");
            }

            if ($copy->status->value !== 'available') {
                throw new RuntimeException("Copy {$copy->barcode} is currently {$copy->status->label()} and cannot be checked out.");
            }

            if ($copy->book->is_reference_only) {
                throw new RuntimeException("This item is marked Reference Only and must remain in the library.");
            }

            // Provision or fetch member profile
            $member = $this->memberService->getOrProvisionMember($user);

            // Check borrowing eligibility
            $eligibility = $this->memberService->checkBorrowingEligibility($member);
            if (!$eligibility['eligible']) {
                throw new RuntimeException($eligibility['reason']);
            }

            // Check hold queue reservation priority
            $pendingHolds = Reservation::where('book_id', $copy->book_id)
                ->whereIn('status', ['pending', 'ready_for_pickup'])
                ->orderBy('queue_position')
                ->get();

            if ($pendingHolds->isNotEmpty()) {
                $firstHold = $pendingHolds->first();
                $availableCopiesCount = Copy::where('book_id', $copy->book_id)
                    ->where('status', 'available')
                    ->count();

                if ($pendingHolds->count() >= $availableCopiesCount && $firstHold->member_id !== $member->id) {
                    throw new RuntimeException("This copy is currently reserved for a member in the hold queue.");
                }
            }

            $pendingHold = $pendingHolds->firstWhere('member_id', $member->id);

            $loanDays = $copy->book->default_loan_days ?? 14;

            $loan = Loan::create([
                'copy_id' => $copy->id,
                'member_id' => $member->id,
                'checked_out_by' => $user->id,
                'checked_out_at' => now(),
                'due_at' => now()->addDays($loanDays),
                'status' => 'active',
            ]);

            $copy->update(['status' => 'checked_out']);

            if ($pendingHold && $pendingHold->member_id === $member->id) {
                $pendingHold->update([
                    'status' => 'fulfilled',
                    'fulfilled_at' => now(),
                ]);
            }

            try {
                Mail::to($member->email)->queue(new LoanCheckoutConfirmationMail($loan));
            } catch (\Throwable $e) {
                Log::error("Failed to queue checkout confirmation mail: " . $e->getMessage());
            }

            return $loan->load(['copy.book', 'member']);
        });
    }

    /**
     * Self-service return restricted to the loan owner.
     */
    public function selfServiceReturn(string $copyBarcode, User $user): Loan
    {
        return DB::transaction(function () use ($copyBarcode, $user) {
            $copy = Copy::where('barcode', $copyBarcode)
                ->orWhere('accession_number', $copyBarcode)
                ->orWhere('uuid', $copyBarcode)
                ->lockForUpdate()
                ->first();

            if (!$copy) {
                throw new InvalidArgumentException("Physical copy with barcode '{$copyBarcode}' was not found.");
            }

            $loan = Loan::where('copy_id', $copy->id)->whereIn('status', ['active', 'overdue'])->first();
            if (!$loan) {
                throw new RuntimeException("No active loan checkout record found for copy {$copy->barcode}.");
            }

            $member = Member::where('user_id', $user->id)->first();
            if (!$member || $loan->member_id !== $member->id) {
                throw new RuntimeException("This copy is currently checked out to another member. Please have the borrower return it or contact library staff.");
            }

            return $this->executeReturn($loan, $copy, $user->id);
        });
    }

    /**
     * Administrative return override for staff/admin users.
     */
    public function staffReturnOverride(string $copyBarcode, User $staffUser): Loan
    {
        return DB::transaction(function () use ($copyBarcode, $staffUser) {
            $copy = Copy::where('barcode', $copyBarcode)
                ->orWhere('accession_number', $copyBarcode)
                ->orWhere('uuid', $copyBarcode)
                ->lockForUpdate()
                ->first();

            if (!$copy) {
                throw new InvalidArgumentException("Physical copy with barcode '{$copyBarcode}' was not found.");
            }

            $loan = Loan::where('copy_id', $copy->id)->whereIn('status', ['active', 'overdue'])->first();
            if (!$loan) {
                throw new RuntimeException("No active loan checkout record found for copy {$copy->barcode}.");
            }

            return $this->executeReturn($loan, $copy, $staffUser->id);
        });
    }

    /**
     * Internal return execution and hold fulfillment logic.
     */
    protected function executeReturn(Loan $loan, Copy $copy, int $returnedByUserId): Loan
    {
        $loan->update([
            'status' => 'returned',
            'returned_at' => now(),
            'returned_to' => $returnedByUserId,
        ]);

        $hold = Reservation::where('book_id', $copy->book_id)
            ->where('status', 'pending')
            ->orderBy('queue_position')
            ->first();

        if ($hold) {
            $copy->update(['status' => 'reserved']);
            $hold->update([
                'copy_id' => $copy->id,
                'status' => 'ready_for_pickup',
                'ready_at' => now(),
                'expires_at' => now()->addDays(3),
            ]);

            try {
                Mail::to($hold->member->email)->queue(new HoldReadyForPickupMail($hold));
            } catch (\Throwable $e) {
                Log::error("Failed to queue hold ready mail: " . $e->getMessage());
            }
        } else {
            $copy->update(['status' => 'available']);
        }

        return $loan->load(['copy.book', 'member']);
    }

    /**
     * Renew an active loan.
     */
    public function renewLoan(Loan $loan, User $user): Loan
    {
        return DB::transaction(function () use ($loan, $user) {
            $lockedLoan = Loan::where('id', $loan->id)->lockForUpdate()->firstOrFail();

            if ($lockedLoan->status->value !== 'active') {
                throw new RuntimeException("Only active loans can be renewed.");
            }

            if ($lockedLoan->renewed_count >= 2) {
                throw new RuntimeException("Maximum renewals limit (2) reached for this loan.");
            }

            // Check if holds exist for this book
            $hasHold = Reservation::where('book_id', $lockedLoan->copy->book_id)
                ->where('status', 'pending')
                ->exists();

            if ($hasHold) {
                throw new RuntimeException("Cannot renew because another member has reserved this book.");
            }

            $previousDue = $lockedLoan->due_at;
            $newDue = $previousDue->copy()->addDays(14);

            $lockedLoan->update([
                'due_at' => $newDue,
                'renewed_count' => $lockedLoan->renewed_count + 1,
                'last_renewed_at' => now(),
            ]);

            Renewal::create([
                'loan_id' => $lockedLoan->id,
                'renewed_by' => $user->id,
                'previous_due_at' => $previousDue,
                'new_due_at' => $newDue,
                'created_at' => now(),
            ]);

            return $lockedLoan->load(['copy.book', 'member']);
        });
    }
}
