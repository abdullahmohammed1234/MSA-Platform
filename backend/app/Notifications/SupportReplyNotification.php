<?php

namespace App\Notifications;

use App\Models\SupportInquiry;
use App\Models\SupportMessage;
use Illuminate\Notifications\Messages\MailMessage;

class SupportReplyNotification extends ImmediateNotification
{
    public function __construct(
        public SupportInquiry $inquiry,
        public SupportMessage $message
    ) {}

    public function getCategory(): string
    {
        return 'support_updates';
    }

    public function toMail($notifiable): MailMessage
    {
        $connectUrl = rtrim((string) config('app.connect_url'), '/');
        $inquiryUrl = $connectUrl . '/app/support/' . $this->inquiry->reference_code;

        return (new MailMessage)
            ->subject('Update on Inquiry #' . $this->inquiry->reference_code . ' — SFU MSA Connect')
            ->greeting('Assalamu alaikum ' . ($notifiable->name ?? 'Member') . ',')
            ->line('An SFU MSA coordinator has posted a response to your private support inquiry: "' . $this->inquiry->subject . '".')
            ->line('To protect your privacy and ensure sensitive discussions remain confidential, please view the complete conversation directly inside MSA Connect.')
            ->action('View Support Conversation', $inquiryUrl)
            ->line('Thank you for connecting with the SFU Muslim Students\' Association.');
    }

    public function toArray($notifiable): array
    {
        return [
            'title' => 'Support Update (#' . $this->inquiry->reference_code . ')',
            'message' => 'An MSA coordinator replied to your inquiry: ' . $this->inquiry->subject,
            'inquiry_uuid' => $this->inquiry->uuid,
            'reference_code' => $this->inquiry->reference_code,
            'action_url' => '/app/support/' . $this->inquiry->reference_code,
            'action_label' => 'View Conversation',
            'type' => 'support',
        ];
    }
}
