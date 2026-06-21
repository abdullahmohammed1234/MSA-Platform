<?php

namespace App\Notifications;

use App\Models\CertificateAward;
use Illuminate\Notifications\Messages\MailMessage;

class CertificateEarnedNotification extends BaseNotification
{
    protected CertificateAward $award;

    /**
     * Create a new notification instance.
     */
    public function __construct(CertificateAward $award)
    {
        $this->award = $award;
    }

    /**
     * Get the preference category name.
     */
    public function getCategory(): string
    {
        return 'certificate_earned';
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable): MailMessage
    {
        $frontendUrl = rtrim(config('app.frontend_url'), '/');
        $verifyUrl = $frontendUrl . '/certificates/verify/' . $this->award->verification_token;
        
        $issueDate = now()->format('F j, Y');
        if ($this->award->issued_at) {
            $issueDate = is_string($this->award->issued_at) 
                ? date('F j, Y', strtotime($this->award->issued_at))
                : $this->award->issued_at->format('F j, Y');
        }

        return (new MailMessage)
            ->subject('Congratulations! You earned a certificate')
            ->view('emails.certificate_earned', [
                'name' => $notifiable->name,
                'certificateTitle' => $this->award->title,
                'certificateCode' => $this->award->code,
                'issueDate' => $issueDate,
                'verifyUrl' => $verifyUrl,
            ]);
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray($notifiable): array
    {
        $frontendUrl = rtrim(config('app.frontend_url'), '/');
        $verifyUrl = $frontendUrl . '/certificates/verify/' . $this->award->verification_token;

        return [
            'title' => 'Certificate Earned!',
            'message' => "Congratulations! You have earned the certificate: {$this->award->title}.",
            'award_uuid' => $this->award->uuid,
            'code' => $this->award->code,
            'verify_url' => $verifyUrl,
            'type' => 'certificate',
        ];
    }
}
