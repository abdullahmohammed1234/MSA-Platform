<?php

namespace App\Console\Commands;

use App\Ems\Models\EventNotification;
use App\Volunteering\Models\Signup;
use App\Volunteering\Services\VmsNotificationDispatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class SendVmsRemindersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vms:send-reminders {--dry-run : Only log eligible signups without sending emails}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send configurable upcoming shift reminders to volunteers using deterministic idempotency keys';

    /**
     * Execute the console command.
     */
    public function handle(VmsNotificationDispatcher $dispatcher): int
    {
        if (! config('vms.notifications.reminders.enabled', true)) {
            $this->info('VMS shift reminders are disabled in configuration.');

            return self::SUCCESS;
        }

        $timings = (array) config('vms.notifications.reminders.timings', [24, 2]);
        $isDryRun = (bool) $this->option('dry-run');

        $dispatchedCount = 0;

        foreach ($timings as $hours) {
            $hours = (int) $hours;
            if ($hours <= 0) {
                continue;
            }

            // Window: shifts starting between now and (now + $hours) that have not had this reminder sent
            $maxStart = now()->addHours($hours + 1);
            $minStart = now();

            $signups = Signup::with(['opportunity.event', 'shift', 'team'])
                ->whereIn('status', ['signed_up', 'confirmed'])
                ->whereHas('opportunity', function ($q) {
                    $q->where('status', 'open');
                })
                ->where(function ($query) use ($minStart, $maxStart) {
                    $query->whereHas('shift', function ($sq) use ($minStart, $maxStart) {
                        $sq->where('status', 'open')
                            ->whereNotNull('start_at')
                            ->where('start_at', '>=', $minStart)
                            ->where('start_at', '<=', $maxStart);
                    })->orWhere(function ($oq) use ($minStart, $maxStart) {
                        $oq->whereNull('shift_id')
                            ->whereHas('opportunity', function ($opq) use ($minStart, $maxStart) {
                                $opq->whereNotNull('start_at')
                                    ->where('start_at', '>=', $minStart)
                                    ->where('start_at', '<=', $maxStart);
                            });
                    });
                })
                ->get();

            foreach ($signups as $signup) {
                $idempotencyKey = "vms_shift_reminder:{$signup->id}:{$hours}h";

                // Verify reminder has not already been created/sent
                $alreadySent = EventNotification::where('idempotency_key', $idempotencyKey)->exists();
                if ($alreadySent) {
                    continue;
                }

                if ($isDryRun) {
                    $this->info("[DRY RUN] Would send {$hours}h reminder to {$signup->email} for Signup #{$signup->id}");
                    $dispatchedCount++;
                    continue;
                }

                $dispatcher->notifyShiftReminder($signup, $hours);
                $dispatchedCount++;

                Log::info('vms.reminders.dispatched', [
                    'signup_id' => $signup->id,
                    'hours_before' => $hours,
                    'email' => $signup->email,
                ]);
            }
        }

        $this->info("Processed VMS shift reminders. Total dispatched: {$dispatchedCount}");

        return self::SUCCESS;
    }
}
