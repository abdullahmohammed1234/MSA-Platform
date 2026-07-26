<?php

namespace App\Ems\Services\Notifications;

use App\Ems\Enums\NotificationType;
use App\Ems\Models\NotificationPreference;
use App\Ems\Models\Registration;
use App\Models\User;

/**
 * Resolves whether a non-transactional notification may be delivered.
 */
class PreferenceResolver
{
    public function allows(NotificationType $type, ?Registration $registration = null, ?string $email = null, ?User $user = null): bool
    {
        if ($type->isTransactional()) {
            return true;
        }

        $key = $type->preferenceKey();

        if ($key === null) {
            return true;
        }

        $prefs = $this->resolve($registration, $email, $user);

        if ($prefs === null) {
            // Opt-in defaults: reminders/updates/feedback/post-event on; marketing off.
            return $key !== 'marketing_emails';
        }

        return $prefs->allows($key);
    }

    public function resolve(?Registration $registration = null, ?string $email = null, ?User $user = null): ?NotificationPreference
    {
        $userId = $user?->id ?? $registration?->user_id;
        $resolvedEmail = strtolower(trim((string) ($email ?? $registration?->attendee_email ?? $user?->email ?? '')));

        if ($userId) {
            $byUser = NotificationPreference::query()->where('user_id', $userId)->first();
            if ($byUser !== null) {
                return $byUser;
            }
        }

        if ($resolvedEmail !== '') {
            return NotificationPreference::query()->where('email', $resolvedEmail)->first();
        }

        return null;
    }

    /**
     * @param  array{
     *     event_reminders?: bool,
     *     event_updates?: bool,
     *     feedback_requests?: bool,
     *     marketing_emails?: bool,
     *     post_event?: bool
     * }  $data
     */
    public function upsertForUser(User $user, array $data): NotificationPreference
    {
        $prefs = NotificationPreference::query()->firstOrNew(['user_id' => $user->id]);
        $prefs->email = strtolower((string) $user->email);
        $prefs->fill(array_intersect_key($data, array_flip([
            'event_reminders',
            'event_updates',
            'feedback_requests',
            'marketing_emails',
            'post_event',
        ])));
        $prefs->save();

        return $prefs->fresh();
    }
}
