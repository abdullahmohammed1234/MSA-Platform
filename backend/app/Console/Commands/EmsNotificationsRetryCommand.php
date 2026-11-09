<?php

namespace App\Console\Commands;

use App\Ems\Enums\NotificationStatus;
use App\Ems\Jobs\SendEventNotificationJob;
use App\Ems\Models\EventNotification;
use App\Ems\Models\Registration;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Safe, dry-run-first retry tool for failed or stuck EMS notifications.
 *
 * Retries emails without modifying registration status, charging payments,
 * or issuing duplicate tickets.
 */
class EmsNotificationsRetryCommand extends Command
{
    protected $signature = 'ems:notifications:retry
        {--dry-run : Inspect eligible failed notifications without sending (default)}
        {--id= : Target a specific notification ID}
        {--registration= : Target notifications for a registration reference or UUID}
        {--type= : Filter by notification type (e.g. registration_confirmed, payment_confirmation)}
        {--execute : Explicit flag required to dispatch retries}
        {--limit=50 : Maximum records to process}';

    protected $description = 'Safely inspect and retry failed or pending EMS event notifications (defaults to dry-run)';

    public function handle(): int
    {
        $isExecute = (bool) $this->option('execute') && ! $this->option('dry-run');
        $id = $this->option('id');
        $regTarget = $this->option('registration');
        $type = $this->option('type');
        $limit = max(1, (int) $this->option('limit'));

        $query = EventNotification::query()
            ->whereIn('status', [
                NotificationStatus::Failed->value,
                NotificationStatus::Pending->value,
                NotificationStatus::Scheduled->value,
            ])
            ->where(function ($q) {
                $q->whereNull('scheduled_at')
                  ->orWhere('scheduled_at', '<=', now());
            })
            ->when($id, fn ($q) => $q->where('id', $id))
            ->when($type, fn ($q) => $q->where('type', $type))
            ->when($regTarget, function ($q) use ($regTarget) {
                $registration = Registration::query()
                    ->where('uuid', $regTarget)
                    ->orWhere('reference', $regTarget)
                    ->first();

                if ($registration) {
                    $q->where('registration_id', $registration->id);
                } else {
                    $q->whereRaw('0 = 1');
                }
            })
            ->orderByDesc('id')
            ->limit($limit);

        $notifications = $query->get();

        if ($notifications->isEmpty()) {
            $this->info('No matching failed or pending event notifications found.');
            return self::SUCCESS;
        }

        $this->info(sprintf(
            '%s: Found %d eligible notification(s).',
            $isExecute ? '[EXECUTION MODE]' : '[DRY-RUN MODE]',
            $notifications->count()
        ));

        $rows = [];
        foreach ($notifications as $notification) {
            $rows[] = [
                'ID' => $notification->id,
                'Type' => $notification->type,
                'Status' => $notification->status instanceof NotificationStatus ? $notification->status->value : (string) $notification->status,
                'Recipient' => $notification->recipient_email ? preg_replace('/(?<=.{3}).(?=.*@)/', '*', $notification->recipient_email) : 'None',
                'Event ID' => $notification->event_id,
                'Registration ID' => $notification->registration_id,
                'Retries' => $notification->retry_count,
                'Error' => mb_substr((string) $notification->error, 0, 40),
                'Scheduled At' => (string) $notification->scheduled_at,
            ];
        }

        $this->table(['ID', 'Type', 'Status', 'Recipient', 'Event ID', 'Registration ID', 'Retries', 'Error', 'Scheduled At'], $rows);

        if (! $isExecute) {
            $this->warn('DRY RUN: No emails were dispatched. Run with --execute (and optional --id=<id>) to dispatch.');
            return self::SUCCESS;
        }

        $dispatched = 0;
        foreach ($notifications as $notification) {
            if (blank($notification->recipient_email) || ! filter_var($notification->recipient_email, FILTER_VALIDATE_EMAIL)) {
                $this->warn("Skipping notification #{$notification->id}: invalid or missing recipient email address.");
                continue;
            }

            try {
                // For a failed notification, reset to pending with a queued lease so it can be safely dispatched
                $claimed = EventNotification::query()
                    ->whereKey($notification->id)
                    ->where('status', NotificationStatus::Failed->value)
                    ->update([
                        'status' => NotificationStatus::Pending->value,
                        'queue_status' => 'queued',
                        'queued_at' => now(),
                        'error' => null,
                        'updated_at' => now(),
                    ]) === 1;

                if (! $claimed) {
                    $claimed = EventNotification::claimForDispatch($notification->id);
                }

                if ($claimed) {
                    SendEventNotificationJob::dispatchSync($notification->id);
                    $dispatched++;
                    $this->info("Dispatched notification #{$notification->id} ({$notification->type}) to recipient.");
                } else {
                    $this->warn("Could not claim notification #{$notification->id} (already claimed or completed).");
                }
            } catch (\Throwable $e) {
                $this->error("Failed dispatching notification #{$notification->id}: " . $e->getMessage());
                Log::channel((string) config('ems.logging.channel', 'ems'))
                    ->error('ems.notifications.cli_retry_failed', [
                        'notification_id' => $notification->id,
                        'error' => $e->getMessage(),
                    ]);
            }
        }

        $this->info("Completed. Dispatched {$dispatched} notification(s).");
        return self::SUCCESS;
    }
}
