<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PlatformNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $title;

    protected $message;

    protected $data;

    public function __construct(string $title, string $message, array $data = [])
    {
        $this->title = $title;
        $this->message = $message;
        $this->data = $data;
    }

    public function via($notifiable): array
    {
        return ['database', 'mail'];
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
