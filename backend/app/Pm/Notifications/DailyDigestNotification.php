<?php

namespace App\Pm\Notifications;

use App\Models\User;
use App\Services\Notifications\CustomDatabaseChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DailyDigestNotification extends Notification
{
    use Queueable;

    public function __construct(
        public array $summaryData
    ) {}

    public function via($notifiable): array
    {
        $channels = [CustomDatabaseChannel::class];

        if ($notifiable instanceof User && $notifiable->notificationPreferences) {
            if ($notifiable->notificationPreferences->email_enabled) {
                $channels[] = 'mail';
            }
        } else {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toArray($notifiable): array
    {
        $dueTodayCount = count($this->summaryData['due_today'] ?? []);
        $upcomingCount = count($this->summaryData['upcoming'] ?? []);
        $overdueCount = count($this->summaryData['overdue'] ?? []);

        return [
            'title' => 'Your Daily PMS Digest',
            'message' => "Today's summary: {$dueTodayCount} due today, {$upcomingCount} upcoming, {$overdueCount} overdue.",
            'type' => 'pm.daily_digest',
            'summary' => $this->summaryData,
            'action_url' => '/pm/dashboard',
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        $dueToday = $this->summaryData['due_today'] ?? [];
        $upcoming = $this->summaryData['upcoming'] ?? [];
        $overdue = $this->summaryData['overdue'] ?? [];

        $mail = (new MailMessage)
            ->subject('SFU MSA PMS — Your Daily Digest')
            ->greeting("Assalamu Alaikum {$notifiable->name},")
            ->line("Here is your daily Project Management summary:");

        if (!empty($dueToday)) {
            $mail->line('**Due Today:**');
            foreach (array_slice($dueToday, 0, 5) as $t) {
                $mail->line("• {$t['title']} ({$t['project_name']})");
            }
        }

        if (!empty($upcoming)) {
            $mail->line('**Upcoming Deadlines:**');
            foreach (array_slice($upcoming, 0, 5) as $t) {
                $mail->line("• {$t['title']} — due {$t['due_date']} ({$t['project_name']})");
            }
        }

        if (!empty($overdue)) {
            $mail->line('**Overdue Tasks:**');
            foreach (array_slice($overdue, 0, 5) as $t) {
                $mail->line("• {$t['title']} — was due {$t['due_date']} ({$t['project_name']})");
            }
        }

        if (empty($dueToday) && empty($upcoming) && empty($overdue)) {
            $mail->line('You have no overdue or immediately approaching tasks. Great job!');
        }

        return $mail->action('Open PMS Dashboard', url('/pm/dashboard'))
            ->line('Thank you for your service to SFU MSA!');
    }
}

