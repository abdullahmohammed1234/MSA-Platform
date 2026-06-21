<?php

namespace App\Notifications\Auth;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VerifyEmailNotification extends Notification
{
    protected $token;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $token)
    {
        $this->token = $token;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $frontendUrl = rtrim(config('app.frontend_url'), '/');
        $verificationUrl = "{$frontendUrl}/verify-email?token={$this->token}";

        return (new MailMessage)
            ->subject('Verify Your Email Address - SFU MSA')
            ->greeting("Assalamu Alaikum, {$notifiable->name}!")
            ->line('Thank you for registering an account on the SFU MSA Platform. Please verify your email address to get access to the Dawah Academy and other features.')
            ->action('Verify Email Address', $verificationUrl)
            ->line("If you cannot click the button above, you can copy and paste the following token directly on the verification page: {$this->token}")
            ->line('This verification link will expire in 60 minutes.')
            ->line('If you did not create an account, no further action is required.')
            ->salutation('Warm regards,  \nThe SFU MSA Team');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'token' => $this->token,
        ];
    }
}
