<?php

namespace App\Ems\Http\Requests\Notifications;

use App\Ems\Http\Requests\EmsFormRequest;

class UpdateNotificationPreferencesRequest extends EmsFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'event_reminders' => ['sometimes', 'boolean'],
            'event_updates' => ['sometimes', 'boolean'],
            'feedback_requests' => ['sometimes', 'boolean'],
            'marketing_emails' => ['sometimes', 'boolean'],
            'post_event' => ['sometimes', 'boolean'],
        ];
    }
}
