<?php

namespace App\Services;

use App\Models\User;

class NotificationService
{
    public function sendInApp(User $user, string $title, string $message): void
    {
        // Base structure logic for In-App notifications
    }

    public function sendEmail(User $user, string $subject, string $body): void
    {
        // Base structure logic for Email notifications
    }
}
