<?php

namespace App\Notifications;

use App\Models\Conversation;
use App\Models\User;
use App\Services\Messaging\DirectMessagingAuthorizationService;
use App\Services\Notifications\CustomDatabaseChannel;
use Illuminate\Notifications\Messages\MailMessage;

class DirectMessageNotification extends ImmediateNotification
{
    public function __construct(
        public Conversation $conversation,
        public User $sender
    ) {}

    public function getCategory(): string
    {
        return 'direct_messages';
    }

    /**
     * Determine delivery channels based on user preferences and email suppression policy.
     *
     * In-app notifications remain fully independent of email suppression.
     * Email is suppressed if:
     * - The recipient has disabled email notifications or the direct_messages category.
     * - The recipient has recently read the conversation (< 5 minutes).
     * - An email was already sent for this conversation within the 5-minute cooldown window.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable): array
    {
        // 1. Never notify self or non-users
        if (!$notifiable instanceof User || $notifiable->id === $this->sender->id) {
            return [];
        }

        // 2. Check notification preferences
        $preferences = $notifiable->notificationPreferences;

        // If user has explicitly opted out of the direct_messages category, suppress completely
        if ($preferences && isset($preferences->direct_messages) && !$preferences->direct_messages) {
            return [];
        }

        $channels = [];

        // 3. In-App Notification: Independent of email suppression
        $inAppEnabled = !$preferences || $preferences->in_app_enabled;
        if ($inAppEnabled) {
            $channels[] = CustomDatabaseChannel::class;
        }

        // 4. Email Notification: Respects email toggle, recent-read suppression, and 5-min cooldown
        $emailEnabled = !$preferences || $preferences->email_enabled;
        if ($emailEnabled) {
            if (DirectMessagingAuthorizationService::claimEmailNotification($this->conversation, $notifiable)) {
                $channels[] = 'mail';
            }
        }

        // 5. Web Push Notification: Independent of email suppression, respects push toggle
        $pushEnabled = !$preferences || !isset($preferences->push_enabled) || $preferences->push_enabled;
        if ($pushEnabled) {
            $channels[] = \App\Services\Notifications\WebPushChannel::class;
        }

        return $channels;
    }

    public function toMail($notifiable): MailMessage
    {
        $connectUrl = rtrim((string) config('app.connect_url'), '/');
        $conversationUrl = $connectUrl . '/app/messages/' . $this->conversation->uuid;

        return (new MailMessage)
            ->subject('New Private Message from ' . $this->sender->name . ' — SFU MSA')
            ->greeting('Assalamu alaikum ' . ($notifiable->name ?? 'Member') . ',')
            ->line($this->sender->name . ' has sent you a new private direct message.')
            ->line('To protect the privacy and confidentiality of your communications, message details are kept securely inside the platform.')
            ->action('View Conversation', $conversationUrl)
            ->line('Thank you for connecting with the SFU Muslim Students\' Association.');
    }

    public function toArray($notifiable): array
    {
        return [
            'title' => 'New Message from ' . $this->sender->name,
            'message' => 'You received a new direct message from ' . $this->sender->name . '.',
            'conversation_uuid' => $this->conversation->uuid,
            'sender_uuid' => $this->sender->uuid,
            'sender_name' => $this->sender->name,
            'action_url' => '/app/messages/' . $this->conversation->uuid,
            'action_label' => 'View Message',
            'type' => 'direct_message',
        ];
    }
}
