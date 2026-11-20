<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PlatformNotification extends BaseNotification
{
    protected $title;

    protected $message;

    protected $data;

    public function __construct(string $title, string $message, array $data = [])
    {
        $this->title = $title;
        $this->message = $message;
        $this->data = $data;
    }

    public function getCategory(): string
    {
        $type = $this->data['type'] ?? 'event';

        return match ($type) {
            'announcement' => 'new_announcements',
            'course' => 'course_completion',
            'certificate' => 'certificate_earned',
            default => 'upcoming_training',
        };
    }

    public function via($notifiable): array
    {
        $suppressMail = (bool) ($this->data['suppress_mail'] ?? false);

        if (!$notifiable instanceof \App\Models\User) {
            $channels = [\App\Services\Notifications\CustomDatabaseChannel::class, 'mail'];

            return $suppressMail
                ? array_values(array_filter($channels, fn ($channel) => $channel !== 'mail'))
                : $channels;
        }

        $preferences = $notifiable->notificationPreferences;

        if (!$preferences) {
            $channels = [\App\Services\Notifications\CustomDatabaseChannel::class, 'mail'];

            return $suppressMail
                ? array_values(array_filter($channels, fn ($channel) => $channel !== 'mail'))
                : $channels;
        }

        $category = $this->getCategory();

        if (isset($preferences->$category) && !$preferences->$category) {
            return [];
        }

        $channels = [];

        if ($preferences->in_app_enabled) {
            $channels[] = \App\Services\Notifications\CustomDatabaseChannel::class;
        }

        if ($preferences->email_enabled && ! $suppressMail) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title)
            ->line($this->message)
            ->action('View Details', url('/'))
            ->line('Thank you for being part of SFU MSA!');
    }

    public function toArray($notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'data' => $this->data,
        ];
    }
}
