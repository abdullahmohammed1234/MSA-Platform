<?php

namespace App\Notifications;

use App\Models\AnalyticsReport;
use Illuminate\Notifications\Messages\MailMessage;

class SendAnalyticsReportNotification extends BaseNotification
{
    protected AnalyticsReport $report;

    /**
     * Create a new notification instance.
     */
    public function __construct(AnalyticsReport $report)
    {
        $this->report = $report;
    }

    /**
     * Get the preference category name.
     */
    public function getCategory(): string
    {
        return 'new_announcements';
    }

    /**
     * Override via to force database and mail delivery for admin alerts.
     */
    public function via($notifiable): array
    {
        return ['mail', \App\Services\Notifications\CustomDatabaseChannel::class];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("System Analytics Report: {$this->report->title}")
            ->line("Hello {$notifiable->name},")
            ->line("The scheduled analytics report '{$this->report->title}' (Type: {$this->report->type}) has been successfully generated.")
            ->line("You can review the detailed analytics by logging into the administration panel.");

        if ($this->report->file_path && file_exists(storage_path('app/' . $this->report->file_path))) {
            $mail->attach(storage_path('app/' . $this->report->file_path), [
                'as' => basename($this->report->file_path),
                'mime' => 'application/pdf',
            ]);
        }

        return $mail;
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray($notifiable): array
    {
        return [
            'title' => 'Analytics Report Generated',
            'message' => "The scheduled analytics report '{$this->report->title}' is ready.",
            'report_uuid' => $this->report->uuid,
            'type' => 'analytics_report',
        ];
    }
}
