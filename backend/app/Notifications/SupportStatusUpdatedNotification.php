<?php

namespace App\Notifications;

use App\Models\SupportInquiry;
use Illuminate\Notifications\Messages\MailMessage;

class SupportStatusUpdatedNotification extends ImmediateNotification
{
    public function __construct(
        public SupportInquiry $inquiry,
        public string $previousStatus,
        public string $newStatus
    ) {}

    public function getCategory(): string
    {
        return 'support_updates';
    }

    public function toMail($notifiable): MailMessage
    {
        $connectUrl = rtrim((string) config('app.connect_url'), '/');
        $inquiryUrl = $connectUrl . '/app/support/' . $this->inquiry->reference_code;
        $readableStatus = ucwords(str_replace('_', ' ', $this->newStatus));

        return (new MailMessage)
            ->subject('Inquiry #' . $this->inquiry->reference_code . ' Status: ' . $readableStatus . ' — SFU MSA Connect')
            ->greeting('Assalamu alaikum ' . ($notifiable->name ?? 'Member') . ',')
            ->line('The status of your support inquiry ("' . $this->inquiry->subject . '") has been updated to: ' . $readableStatus . '.')
            ->action('View Support Conversation', $inquiryUrl)
            ->line('Thank you for connecting with the SFU Muslim Students\' Association.');
    }

    public function toArray($notifiable): array
    {
        $readableStatus = ucwords(str_replace('_', ' ', $this->newStatus));

        return [
            'title' => 'Inquiry Updated (#' . $this->inquiry->reference_code . ')',
            'message' => 'Your inquiry status has been updated to ' . $readableStatus . '.',
            'inquiry_uuid' => $this->inquiry->uuid,
            'reference_code' => $this->inquiry->reference_code,
            'action_url' => '/app/support/' . $this->inquiry->reference_code,
            'action_label' => 'View Conversation',
            'type' => 'support',
            'status' => $this->newStatus,
        ];
    }
}
