<?php

namespace App\Donations\Notifications;

use App\Donations\Models\Donation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DonationConfirmationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Donation $donation
    ) {}

    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $amountFormatted = '$'.number_format($this->donation->amount_cents / 100, 2).' CAD';
        
        return (new MailMessage)
            ->subject('Thank you for your donation to SFU MSA — Receipt '.$this->donation->donation_number)
            ->greeting('Jazakum Allahu Khairan '.$this->donation->donor_name.',')
            ->line('Thank you for your generous contribution of '.$amountFormatted.' to the SFU Muslim Students Association.')
            ->line('Donation Number: '.$this->donation->donation_number)
            ->line('Date: '.($this->donation->paid_at ? $this->donation->paid_at->toFormattedDateString() : now()->toFormattedDateString()))
            ->line('Your support enables us to continue serving the SFU student community with events, programs, and services.')
            ->line('May Allah (SWT) reward you abundantly in this life and the next.');
    }
}
