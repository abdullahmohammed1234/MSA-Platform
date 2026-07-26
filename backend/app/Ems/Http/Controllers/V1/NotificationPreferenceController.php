<?php

namespace App\Ems\Http\Controllers\V1;

use App\Ems\Http\Controllers\EmsController;
use App\Ems\Http\Requests\Notifications\UpdateNotificationPreferencesRequest;
use App\Ems\Services\Notifications\PreferenceResolver;
use App\Ems\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationPreferenceController extends EmsController
{
    public function __construct(private readonly PreferenceResolver $preferences)
    {
    }

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $prefs = $this->preferences->resolve(user: $user);

        return ApiResponse::success([
            'event_reminders' => $prefs?->event_reminders ?? true,
            'event_updates' => $prefs?->event_updates ?? true,
            'feedback_requests' => $prefs?->feedback_requests ?? true,
            'marketing_emails' => $prefs?->marketing_emails ?? false,
            'post_event' => $prefs?->post_event ?? true,
        ], 'Notification preferences retrieved.');
    }

    public function update(UpdateNotificationPreferencesRequest $request): JsonResponse
    {
        $prefs = $this->preferences->upsertForUser($request->user(), $request->validated());

        return ApiResponse::success([
            'event_reminders' => $prefs->event_reminders,
            'event_updates' => $prefs->event_updates,
            'feedback_requests' => $prefs->feedback_requests,
            'marketing_emails' => $prefs->marketing_emails,
            'post_event' => $prefs->post_event,
        ], 'Notification preferences updated.');
    }
}
