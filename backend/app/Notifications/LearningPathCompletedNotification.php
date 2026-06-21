<?php

namespace App\Notifications;

use App\Models\LearningPath;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LearningPathCompletedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $learningPath;

    public function __construct(LearningPath $learningPath)
    {
        $this->learningPath = $learningPath;
    }

    public function via($notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $frontendUrl = rtrim(config('app.frontend_url'), '/');

        return (new MailMessage)
            ->subject("Learning Path Completed: {$this->learningPath->title}!")
            ->greeting("Assalamu Alaikum {$notifiable->name},")
            ->line("SubhanAllah! You have completed the entire learning path: **{$this->learningPath->title}**.")
            ->line("This is an outstanding achievement showing consistency and dedication to Dawah studies.")
            ->action('View Certificates', $frontendUrl . '/academy/certificates')
            ->line('Keep up the amazing work!')
            ->line('Thank you for being part of SFU MSA Dawah Academy.');
    }

    public function toArray($notifiable): array
    {
        return [
            'title' => 'Learning Path Completed!',
            'message' => "SubhanAllah! You have completed the learning path: {$this->learningPath->title}.",
            'type' => 'learning_path',
            'learning_path_uuid' => $this->learningPath->uuid,
            'title_text' => $this->learningPath->title,
        ];
    }
}
