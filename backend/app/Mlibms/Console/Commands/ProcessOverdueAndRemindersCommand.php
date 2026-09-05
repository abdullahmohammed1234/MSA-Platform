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
            try {
                Mail::to($loan->member->email)->queue(new LoanDueDateReminderMail($loan));
                
                // Atomically update timestamp ONLY after successful queue dispatch
                $loan->update(['reminder_sent_at' => now()]);
                $count++;
            } catch (\Throwable $e) {
                Log::error("Failed to queue 2-day due reminder for loan {$loan->uuid}: " . $e->getMessage());
                // Intentionally omit reminder_sent_at update to remain retry-eligible
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
            $loan->update(['status' => 'overdue']);
            try {
                Mail::to($loan->member->email)->queue(new LoanOverdueNoticeMail($loan));
            } catch (\Throwable $e) {
                Log::error("Failed to queue overdue notice for loan {$loan->uuid}: " . $e->getMessage());
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
        foreach ($expiredHolds as $hold) {
            DB::transaction(function () use ($hold, &$count) {
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
