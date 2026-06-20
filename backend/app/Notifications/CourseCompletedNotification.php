<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

class CourseCompletedNotification extends BaseNotification
{
    protected string $courseName;
    protected ?string $completionDate;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $courseName, ?string $completionDate = null)
    {
        $this->courseName = $courseName;
        $this->completionDate = $completionDate;
    }

    /**
     * Get the preference category name.
     */
    public function getCategory(): string
    {
        return 'course_completion';
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable): MailMessage
    {
        $frontendUrl = env('FRONTEND_URL', 'http://localhost:5173');
        $dashboardUrl = $frontendUrl . '/academy/dashboard';

        return (new MailMessage)
            ->subject('Congratulations! Course Completed')
            ->view('emails.course_completed', [
                'name' => $notifiable->name,
                'courseName' => $this->courseName,
                'completionDate' => $this->completionDate ?? now()->format('F j, Y'),
                'dashboardUrl' => $dashboardUrl,
            ]);
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray($notifiable): array
    {
        return [
            'title' => 'Course Completed!',
            'message' => "Congratulations! You have successfully completed {$this->courseName}.",
            'course_name' => $this->courseName,
            'completion_date' => $this->completionDate ?? now()->format('Y-m-d H:i:s'),
            'type' => 'course_completion',
        ];
    }
}
