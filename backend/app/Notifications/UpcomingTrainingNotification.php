<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

class UpcomingTrainingNotification extends BaseNotification
{
    protected string $trainingTitle;
    protected string $trainingDate;
    protected string $trainingLocation;
    protected string $trainingDescription;
    protected string $trainingSlug;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $trainingTitle, string $trainingDate, string $trainingLocation, string $trainingDescription = '', string $trainingSlug = '')
    {
        $this->trainingTitle = $trainingTitle;
        $this->trainingDate = $trainingDate;
        $this->trainingLocation = $trainingLocation;
        $this->trainingDescription = $trainingDescription;
        $this->trainingSlug = $trainingSlug;
    }

    /**
     * Get the preference category name.
     */
    public function getCategory(): string
    {
        return 'upcoming_training';
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable): MailMessage
    {
        $frontendUrl = env('FRONTEND_URL', 'http://localhost:5173');
        $trainingUrl = $frontendUrl . '/training/' . $this->trainingSlug;

        return (new MailMessage)
            ->subject('Upcoming Training Reminder: ' . $this->trainingTitle)
            ->view('emails.upcoming_training', [
                'name' => $notifiable->name,
                'trainingTitle' => $this->trainingTitle,
                'trainingDate' => $this->trainingDate,
                'trainingLocation' => $this->trainingLocation,
                'trainingDescription' => $this->trainingDescription,
                'trainingUrl' => $trainingUrl,
            ]);
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray($notifiable): array
    {
        return [
            'title' => 'Upcoming Training',
            'message' => "Reminder: The session \"{$this->trainingTitle}\" starts on {$this->trainingDate}.",
            'training_title' => $this->trainingTitle,
            'training_date' => $this->trainingDate,
            'training_location' => $this->trainingLocation,
            'training_slug' => $this->trainingSlug,
            'type' => 'upcoming_training',
        ];
    }
}
