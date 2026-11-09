<?php

namespace App\Ems\Services\Notifications;

use App\Ems\Mail\RegistrationEmailFailedAlertMail;
use App\Ems\Models\EventNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Throwable;

class NotificationFailureAlertService
{
    public function shouldAlert(EventNotification $notification): bool
    {
        if ($notification->type !== 'registration_confirmed') {
            return false;
        }

        $row = EventNotification::query()
            ->whereKey($notification->id)
            ->first(['alert_sent_at', 'alert_claimed_at']);

        if (! $row || $row->alert_sent_at !== null) {
            return false;
        }

        // Check if an active, unexpired claim is currently held by another process
        if ($row->alert_claimed_at !== null) {
            $leaseSeconds = EventNotification::alertLeaseSeconds();
            $expiresAt = $row->alert_claimed_at->copy()->addSeconds($leaseSeconds);
            if ($expiresAt->isFuture()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Atomically claim the right to dispatch an alert for this notification.
     * Returns true if claimed, false if another worker holds an active claim or alert was already sent.
     */
    public function claimAlert(EventNotification $notification): bool
    {
        if (! $this->shouldAlert($notification)) {
            return false;
        }

        $now = now();
        $leaseSeconds = EventNotification::alertLeaseSeconds();
        $expirationThreshold = $now->copy()->subSeconds($leaseSeconds);

        if (Schema::hasColumn('ems_notifications', 'alert_claimed_at')) {
            $affected = EventNotification::query()
                ->whereKey($notification->id)
                ->whereNull('alert_sent_at')
                ->where(function ($q) use ($expirationThreshold) {
                    $q->whereNull('alert_claimed_at')
                        ->orWhere('alert_claimed_at', '<=', $expirationThreshold);
                })
                ->update([
                    'alert_claimed_at' => $now,
                    'updated_at' => $now,
                ]);

            if ($affected === 1) {
                $notification->refresh();
                return true;
            }

            return false;
        }

        // Cache-based atomic lock fallback if schema column is absent
        $lock = Cache::lock("ems:alert:claim:{$notification->id}", $leaseSeconds);
        return (bool) $lock->get();
    }

    /**
     * Releases an alert claim following a definite failure so it can be retried immediately.
     */
    public function releaseAlertClaim(EventNotification $notification): void
    {
        if (Schema::hasColumn('ems_notifications', 'alert_claimed_at')) {
            EventNotification::query()
                ->whereKey($notification->id)
                ->whereNull('alert_sent_at')
                ->update([
                    'alert_claimed_at' => null,
                ]);
        } else {
            Cache::lock("ems:alert:claim:{$notification->id}")->forceRelease();
        }

        $notification->refresh();
    }

    public function sendAlert(EventNotification $notification, string $errorMessage): bool
    {
        // 1. Concurrency-safe atomic claim before sending
        if (! $this->claimAlert($notification)) {
            Log::channel('ems')->info('ems.notifications.alert_claim_rejected', [
                'notification_id' => $notification->id,
                'reason' => 'Alert already sent or currently claimed by concurrent worker',
            ]);
            return false;
        }

        $recipientsString = (string) config('ems.notifications.admin_alert_recipients');
        if (empty(trim($recipientsString))) {
            Log::channel('ems')->warning("EMS notification alert recipients config is empty. Skipping admin alert for notification ID: {$notification->id}");
            $this->releaseAlertClaim($notification);
            return false;
        }

        // Split by commas, trim, and filter out invalid/empty entries
        $recipients = collect(explode(',', $recipientsString))
            ->map(fn($email) => trim($email))
            ->filter(fn($email) => filter_var($email, FILTER_VALIDATE_EMAIL))
            ->values()
            ->all();

        if (empty($recipients)) {
            Log::channel('ems')->warning("No valid emails found in EMS alert recipients config: '{$recipientsString}'");
            $this->releaseAlertClaim($notification);
            return false;
        }

        try {
            // Send the failure alert email to all valid recipients
            Mail::to($recipients)->send(new RegistrationEmailFailedAlertMail($notification, $errorMessage));

            // Record alert sent to permanently prevent duplicate alerts
            $this->recordAlertSent($notification);

            return true;
        } catch (Throwable $e) {
            // Check whether transport outcome is uncertain vs definite failure
            if ($this->isUncertainTransportOutcome($e)) {
                // Uncertain transport outcome:
                // Socket timeout after DATA, connection reset mid-session, etc.
                // Do NOT release claim immediately, as the mail server might have accepted the message.
                // Keep the lease active to prevent duplicate emails; it will expire naturally after the lease window.
                Log::channel('ems')->warning("EMS admin alert email transport outcome is uncertain: {$e->getMessage()}", [
                    'notification_id' => $notification->id,
                    'original_error' => $errorMessage,
                    'exception' => $e::class,
                    'claim_retained_until_lease_expiry' => true,
                ]);
            } else {
                // Definite transport failure:
                // e.g. Connection refused, invalid credentials, DNS failure.
                // Release the claim immediately so subsequent attempts are not blocked.
                $this->releaseAlertClaim($notification);

                Log::channel('ems')->error("Failed to send EMS admin failure alert email (definite transport failure): " . $e->getMessage(), [
                    'notification_id' => $notification->id,
                    'original_error' => $errorMessage,
                    'exception' => $e::class,
                    'claim_released' => true,
                ]);
            }

            return false;
        }
    }

    public function recordAlertSent(EventNotification $notification): void
    {
        $now = now();
        $updates = [
            'alert_sent_at' => $now,
        ];

        if (Schema::hasColumn('ems_notifications', 'alert_claimed_at')) {
            $updates['alert_claimed_at'] = null;
        }

        EventNotification::query()
            ->whereKey($notification->id)
            ->whereNull('alert_sent_at')
            ->update($updates);

        $notification->refresh();
    }

    /**
     * Determines whether an SMTP/mailer transport exception represents an uncertain delivery outcome.
     * For example, timeouts after DATA command or socket read timeouts where message may have reached relay.
     */
    public function isUncertainTransportOutcome(Throwable $e): bool
    {
        $msg = strtolower($e->getMessage());

        $uncertainPatterns = [
            'timed out',
            'timeout',
            'connection reset by peer',
            'broken pipe',
            'stream_socket_client',
            'operation timed out',
            'response could not be read',
            'connection closed unexpectedly',
        ];

        foreach ($uncertainPatterns as $pattern) {
            if (str_contains($msg, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determines whether a mail transport failure is definitive.
     */
    public function isDefiniteTransportFailure(Throwable $e): bool
    {
        return ! $this->isUncertainTransportOutcome($e);
    }
}
