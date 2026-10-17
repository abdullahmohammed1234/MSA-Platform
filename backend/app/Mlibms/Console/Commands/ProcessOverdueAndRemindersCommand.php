<?php

namespace App\Mlibms\Console\Commands;

use App\Mlibms\Mail\HoldExpiredMail;
use App\Mlibms\Mail\HoldReadyForPickupMail;
use App\Mlibms\Mail\LoanDueDateReminderMail;
use App\Mlibms\Mail\LoanOverdueNoticeMail;
use App\Mlibms\Models\Copy;
use App\Mlibms\Models\Loan;
use App\Mlibms\Models\Reservation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ProcessOverdueAndRemindersCommand extends Command
{
    protected $signature = 'mlibms:process-overdue-and-reminders';

    protected $description = 'Processes 2-day due-date reminders, overdue loan notices, member suspensions, and expired hold pickups for MLibMS.';

    public function handle(): int
    {
        if (! config('features.mlibms_overdue_processing_enabled', false)) {
            $this->info('MLibMS overdue processing is disabled (MLIBMS_OVERDUE_PROCESSING_ENABLED).');

            return self::SUCCESS;
        }

        $this->info('Starting MLibMS daily overdue and reminder processing...');

        $reminderCount = $this->processTwoDayReminders();
        $overdueCount = $this->processOverdueLoans();
        $suspensionCount = $this->processMemberSuspensions();
        $expiredHoldCount = $this->processExpiredHolds();

        $this->info("MLibMS processing complete: {$reminderCount} reminders queued, {$overdueCount} marked overdue, {$suspensionCount} members suspended, {$expiredHoldCount} expired holds cleaned.");

        return Command::SUCCESS;
    }

    /**
     * Process 2-calendar-day due reminders with retry safety.
     */
    protected function processTwoDayReminders(): int
    {
        $targetDate = now()->addDays(2)->toDateString();

        $eligibleLoans = Loan::where('status', 'active')
            ->whereNull('returned_at')
            ->whereNull('reminder_sent_at')
            ->whereDate('due_at', $targetDate)
            ->get();

        $count = 0;
        foreach ($eligibleLoans as $loan) {
            $claimedAt = now();
            $claimed = Loan::whereKey($loan->id)
                ->where('status', 'active')
                ->whereNull('returned_at')
                ->whereNull('reminder_sent_at')
                ->update(['reminder_sent_at' => $claimedAt]) === 1;

            if (! $claimed) {
                continue;
            }

            try {
                Mail::to($loan->member->email)->queue(new LoanDueDateReminderMail($loan->fresh()));
                $count++;
            } catch (\Throwable $e) {
                Log::error('mlibms.reminder_queue_failed', ['loan_uuid' => $loan->uuid, 'exception' => $e::class]);
                // Release the claim so the loan stays retry-eligible.
                Loan::whereKey($loan->id)->where('reminder_sent_at', $claimedAt)->update(['reminder_sent_at' => null]);
            }
        }

        return $count;
    }

    /**
     * Update active loans past due date to overdue status and send notices.
     */
    protected function processOverdueLoans(): int
    {
        $overdueLoans = Loan::where('status', 'active')
            ->whereNull('returned_at')
            ->where('due_at', '<', now())
            ->get();

        $count = 0;
        foreach ($overdueLoans as $loan) {
            $claimed = Loan::whereKey($loan->id)
                ->where('status', 'active')
                ->whereNull('returned_at')
                ->update(['status' => 'overdue', 'updated_at' => now()]) === 1;

            if (! $claimed) {
                continue;
            }

            try {
                Mail::to($loan->member->email)->queue(new LoanOverdueNoticeMail($loan->fresh()));
            } catch (\Throwable $e) {
                Log::error('mlibms.overdue_notice_queue_failed', ['loan_uuid' => $loan->uuid, 'exception' => $e::class]);
                // Revert so the next run retries the notice instead of silently dropping it.
                Loan::whereKey($loan->id)->where('status', 'overdue')->update(['status' => 'active']);
                continue;
            }
            $count++;
        }

        return $count;
    }

    /**
     * Auto-suspend members with loans overdue by more than 7 days.
     */
    protected function processMemberSuspensions(): int
    {
        $severelyOverdueLoans = Loan::whereIn('status', ['active', 'overdue'])
            ->whereNull('returned_at')
            ->where('due_at', '<', now()->subDays(7))
            ->with('member')
            ->get();

        $suspendedMemberIds = [];
        foreach ($severelyOverdueLoans as $loan) {
            $member = $loan->member;
            if ($member && $member->status !== 'suspended') {
                $member->update([
                    'status' => 'suspended',
                    'suspended_at' => now(),
                    'suspension_reason' => 'Auto-suspended: Has library items overdue by more than 7 days.',
                ]);
                $suspendedMemberIds[] = $member->id;
            }
        }

        return count(array_unique($suspendedMemberIds));
    }

    /**
     * Clean up expired hold reservations.
     */
    protected function processExpiredHolds(): int
    {
        $expiredHolds = Reservation::where('status', 'ready_for_pickup')
            ->where('expires_at', '<', now())
            ->get();

        $count = 0;
        foreach ($expiredHolds as $candidate) {
            DB::transaction(function () use ($candidate, &$count) {
                // Re-check under lock: a concurrent run or a pickup may have
                // already moved this hold on.
                $hold = Reservation::whereKey($candidate->id)
                    ->where('status', 'ready_for_pickup')
                    ->where('expires_at', '<', now())
                    ->lockForUpdate()
                    ->first();

                if ($hold === null) {
                    return;
                }

                $hold->update([
                    'status' => 'expired',
                ]);

                try {
                    Mail::to($hold->member->email)->queue(new HoldExpiredMail($hold));
                } catch (\Throwable $e) {
                    Log::error("Failed to queue hold expired notice for reservation {$hold->uuid}: " . $e->getMessage());
                }

                $copy = $hold->copy;
                if ($copy) {
                    // Check if another hold is waiting in queue
                    $nextHold = Reservation::where('book_id', $hold->book_id)
                        ->where('status', 'pending')
                        ->orderBy('queue_position')
                        ->lockForUpdate()
                        ->first();

                    if ($nextHold) {
                        $nextHold->update([
                            'copy_id' => $copy->id,
                            'status' => 'ready_for_pickup',
                            'ready_at' => now(),
                            'expires_at' => now()->addDays(3),
                        ]);
                        try {
                            Mail::to($nextHold->member->email)->queue(new HoldReadyForPickupMail($nextHold));
                        } catch (\Throwable $e) {
                            Log::error("Failed to queue hold ready mail for next hold: " . $e->getMessage());
                        }
                    } else {
                        $copy->update(['status' => 'available']);
                    }
                }

                $count++;
            });
        }

        return $count;
    }
}
