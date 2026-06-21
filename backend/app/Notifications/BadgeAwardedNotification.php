<?php

namespace App\Notifications;

use App\Models\BadgeAward;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BadgeAwardedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $award;

    public function __construct(BadgeAward $award)
    {
        $this->award = $award;
    }

    public function via($notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $badge = $this->award->badge;
        $frontendUrl = rtrim(config('app.frontend_url'), '/');

        return (new MailMessage)
            ->subject("New Badge Earned: {$badge->name}!")
            ->greeting("Assalamu Alaikum {$notifiable->name},")
            ->line("Excellent work! You have unlocked a new badge: **{$badge->name}**.")
            ->line("Description: {$badge->description}")
            ->action('View Badges Dashboard', $frontendUrl . '/academy/dashboard')
            ->line('Keep learning and unlocking more rewards!')
            ->line('Thank you for being part of SFU MSA Dawah Academy.');
    }

    public function toArray($notifiable): array
    {
        $badge = $this->award->badge;
        return [
            'title' => 'New Badge Awarded!',
            'message' => "You have earned the badge: {$badge->name}.",
            'type' => 'badge',
            'badge_uuid' => $badge->uuid,
            'name' => $badge->name,
        ];
    }
}
