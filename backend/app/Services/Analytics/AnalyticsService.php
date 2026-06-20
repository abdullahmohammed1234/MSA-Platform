<?php

namespace App\Services\Analytics;

use App\Models\AnalyticsSession;
use App\Jobs\Analytics\TrackEventJob;
use Illuminate\Support\Str;
use Carbon\Carbon;

class AnalyticsService
{
    /**
     * Start or update a tracking session.
     */
    public function syncSession(string $uuid, ?int $userId = null, array $details = []): AnalyticsSession
    {
        $session = AnalyticsSession::where('uuid', $uuid)->first();

        if ($session) {
            $updateData = [];
            if ($userId && !$session->user_id) {
                $updateData['user_id'] = $userId;
            }
            
            // Update ended_at and duration
            $now = Carbon::now();
            $startedAt = $session->started_at;
            $duration = (int) abs($now->diffInSeconds($startedAt));
            $updateData['ended_at'] = $now;
            $updateData['duration'] = $duration;

            if (!empty($details['browser'])) $updateData['browser'] = $details['browser'];
            if (!empty($details['platform'])) $updateData['platform'] = $details['platform'];
            if (!empty($details['device'])) $updateData['device'] = $details['device'];
            if (!empty($details['referrer'])) $updateData['referrer'] = $details['referrer'];

            $session->update($updateData);
        } else {
            $session = AnalyticsSession::create([
                'uuid' => $uuid,
                'user_id' => $userId,
                'started_at' => Carbon::now(),
                'ended_at' => Carbon::now(),
                'duration' => 0,
                'device' => $details['device'] ?? null,
                'browser' => $details['browser'] ?? null,
                'platform' => $details['platform'] ?? null,
                'referrer' => $details['referrer'] ?? null,
            ]);
        }

        return $session;
    }

    /**
     * Centralized event dispatch.
     */
    public function track(array $data): void
    {
        $data['uuid'] = $data['uuid'] ?? (string) Str::uuid();
        $data['occurred_at'] = $data['occurred_at'] ?? now()->toDateTimeString();

        // Dispatch background processing job to save DB writes
        TrackEventJob::dispatch($data);
    }

    public function trackPageView(string $url, string $title, ?string $sessionUuid = null, ?int $userId = null, array $metadata = []): void
    {
        $this->track([
            'user_id' => $userId,
            'session_uuid' => $sessionUuid,
            'module' => 'website',
            'event_type' => 'page_view',
            'event_name' => 'page_view',
            'metadata' => array_merge($metadata, [
                'url' => $url,
                'title' => $title,
            ]),
        ]);
    }

    public function trackCourseCompletion(int $userId, int $courseId, ?string $sessionUuid = null, array $metadata = []): void
    {
        $this->track([
            'user_id' => $userId,
            'session_uuid' => $sessionUuid,
            'module' => 'academy',
            'event_type' => 'conversion',
            'event_name' => 'course_completed',
            'entity_type' => 'App\Models\Course',
            'entity_id' => $courseId,
            'metadata' => $metadata,
        ]);
    }

    public function trackQuizSubmission(int $userId, int $quizId, float $score, bool $passed, ?string $sessionUuid = null, array $metadata = []): void
    {
        $this->track([
            'user_id' => $userId,
            'session_uuid' => $sessionUuid,
            'module' => 'academy',
            'event_type' => 'submission',
            'event_name' => 'quiz_submitted',
            'entity_type' => 'App\Models\Quiz',
            'entity_id' => $quizId,
            'metadata' => array_merge($metadata, [
                'score' => $score,
                'passed' => $passed,
            ]),
        ]);
    }

    public function trackCertificateAward(int $userId, int $certificateId, ?string $sessionUuid = null, array $metadata = []): void
    {
        $this->track([
            'user_id' => $userId,
            'session_uuid' => $sessionUuid,
            'module' => 'academy',
            'event_type' => 'award',
            'event_name' => 'certificate_awarded',
            'entity_type' => 'App\Models\Certificate',
            'entity_id' => $certificateId,
            'metadata' => $metadata,
        ]);
    }

    public function trackEventRegistration(int $userId, int $eventId, ?string $sessionUuid = null, array $metadata = []): void
    {
        $this->track([
            'user_id' => $userId,
            'session_uuid' => $sessionUuid,
            'module' => 'events',
            'event_type' => 'conversion',
            'event_name' => 'event_registered',
            'entity_type' => 'App\Models\CMS\Event',
            'entity_id' => $eventId,
            'metadata' => $metadata,
        ]);
    }
}
