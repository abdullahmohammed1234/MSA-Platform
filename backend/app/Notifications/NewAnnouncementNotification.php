<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

class NewAnnouncementNotification extends BaseNotification
{
    protected string $announcementTitle;
    protected string $announcementExcerpt;
    protected string $announcementSlug;
    protected string $audience;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $announcementTitle, string $announcementExcerpt, string $announcementSlug, string $audience = 'All')
    {
        $this->announcementTitle = $announcementTitle;
        $this->announcementExcerpt = $announcementExcerpt;
        $this->announcementSlug = $announcementSlug;
        $this->audience = $audience;
    }

    /**
     * Get the preference category name.
     */
    public function getCategory(): string
    {
        return 'new_announcements';
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable): MailMessage
    {
        $frontendUrl = rtrim(config('app.frontend_url'), '/');
        $announcementUrl = $frontendUrl . '/announcements/' . $this->announcementSlug;

        return (new MailMessage)
            ->subject('New Announcement: ' . $this->announcementTitle)
            ->view('emails.new_announcement', [
                'name' => $notifiable->name,
                'announcementTitle' => $this->announcementTitle,
                'announcementExcerpt' => $this->announcementExcerpt,
                'announcementUrl' => $announcementUrl,
            ]);
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray($notifiable): array
    {
        return [
            'title' => 'New Announcement',
            'message' => $this->announcementTitle,
            'excerpt' => $this->announcementExcerpt,
            'slug' => $this->announcementSlug,
            'audience' => $this->audience,
            'type' => 'announcement',
        ];
    }
}
