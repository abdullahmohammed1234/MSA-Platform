<?php

namespace App\Ems\Services\Notifications;

use App\Ems\Mail\RegistrationEmailFailedAlertMail;
use App\Ems\Models\EventNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class NotificationFailureAlertService
{
    public function shouldAlert(EventNotification $notification): bool
    {
        // Only alert for registration_confirmed notification failures
        // and ensure we haven't already sent an alert for this record.
        return $notification->type === 'registration_confirmed' 
            && $notification->alert_sent_at === null;
    }

    public function sendAlert(EventNotification $notification, string $errorMessage): void
    {
        if (!$this->shouldAlert($notification)) {
            return;
        }

        $recipientsString = (string) config('ems.notifications.admin_alert_recipients');
        if (empty(trim($recipientsString))) {
            Log::channel('ems')->warning("EMS notification alert recipients config is empty. Skipping admin alert for notification ID: {$notification->id}");
            return;
        }

        // Split by commas, trim, and filter out invalid/empty entries
        $recipients = collect(explode(',', $recipientsString))
            ->map(fn($email) => trim($email))
            ->filter(fn($email) => filter_var($email, FILTER_VALIDATE_EMAIL))
            ->values()
            ->all();

        if (empty($recipients)) {
            Log::channel('ems')->warning("No valid emails found in EMS alert recipients config: '{$recipientsString}'");
            return;
        }

        try {
            // Send the failure alert email to all valid recipients
            Mail::to($recipients)->send(new RegistrationEmailFailedAlertMail($notification, $errorMessage));
            
            // Record alert sent to prevent duplicate alerts/spam
            $this->recordAlertSent($notification);
        } catch (Throwable $e) {
            // Loop prevention: strictly log the failure and do not rethrow or recurse
            Log::channel('ems')->error("Failed to send EMS admin failure alert email: " . $e->getMessage(), [
                'notification_id' => $notification->id,
                'original_error' => $errorMessage,
                'exception' => $e
            ]);
        }
    }

    public function recordAlertSent(EventNotification $notification): void
    {
        $notification->update([
            'alert_sent_at' => now(),
        ]);
    }
}
