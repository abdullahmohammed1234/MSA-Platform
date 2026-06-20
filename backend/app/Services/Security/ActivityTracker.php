<?php

namespace App\Services\Security;

use App\Models\UserActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ActivityTracker
{
    /**
     * Track an active user's educational or administrative action.
     *
     * @param string $activityType
     * @param string $description
     * @param Model|null $subject
     * @param array|null $metadata
     * @param int|null $userId
     * @return UserActivity
     */
    public function track(string $activityType, string $description, ?Model $subject = null, ?array $metadata = null, ?int $userId = null): UserActivity
    {
        $resolvedUserId = $userId ?? Auth::id();

        return UserActivity::create([
            'user_id' => $resolvedUserId,
            'activity_type' => $activityType,
            'description' => $description,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id' => $subject ? $subject->getKey() : null,
            'metadata' => $metadata,
        ]);
    }
}
