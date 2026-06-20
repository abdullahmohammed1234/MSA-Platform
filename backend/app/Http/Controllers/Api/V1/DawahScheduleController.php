<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class DawahScheduleController extends Controller
{
    /**
     * Get the schedule file path.
     */
    private function getSchedulePath(): string
    {
        return storage_path('app/dawah-schedule.json');
    }

    /**
     * Get default mock schedule sessions.
     */
    private function getDefaultSchedule(): array
    {
        return [
            [
                'id' => 'schedule-1',
                'title' => 'Outreach Table Setup',
                'date' => '2026-06-05',
                'time' => '12:30 PM - 2:30 PM',
                'location' => 'Burnaby Campus, AQ near the Renaissance Cafe',
                'lead' => 'Mentor Team',
                'focus' => 'Warm welcomes, literature table, and question triage',
                'notes' => 'Bring pamphlets, Qurans, tablecloth, QR code stand, and snacks.',
            ],
            [
                'id' => 'schedule-2',
                'title' => 'Islam Buffet Prep Circle',
                'date' => '2026-06-10',
                'time' => '5:00 PM - 6:30 PM',
                'location' => 'SUB 3210 Musalla',
                'lead' => 'Education & Outreach',
                'focus' => 'Short answers, hospitality flow, and booth assignments',
                'notes' => 'Review common questions and confirm dietary labels before event day.',
            ],
            [
                'id' => 'schedule-3',
                'title' => 'Weekly Dawah Debrief',
                'date' => '2026-06-14',
                'time' => '4:00 PM - 5:00 PM',
                'location' => 'Online / Discord Study Room',
                'lead' => 'Senior Mentors',
                'focus' => 'Reflective practice, scenario review, and follow-up tasks',
                'notes' => 'Students may observe; mentors update action items after the meeting.',
            ],
        ];
    }

    /**
     * Read the schedule sessions.
     */
    private function readSchedule(): array
    {
        $path = $this->getSchedulePath();
        if (!File::exists($path)) {
            File::ensureDirectoryExists(dirname($path));
            File::put($path, json_encode(['sessions' => $this->getDefaultSchedule()], JSON_PRETTY_PRINT));
            return $this->getDefaultSchedule();
        }

        $content = File::get($path);
        $data = json_decode($content, true);
        return $data['sessions'] ?? $this->getDefaultSchedule();
    }

    /**
     * Write schedule sessions to disk.
     */
    private function writeSchedule(array $sessions): void
    {
        $path = $this->getSchedulePath();
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode([
            'updatedAt' => now()->toIso8601String(),
            'sessions' => $sessions
        ], JSON_PRETTY_PRINT));
    }

    /**
     * Check if active user can edit the schedule.
     */
    private function canUserEdit(Request $request): bool
    {
        $user = $request->user();
        if (!$user) {
            return false;
        }
        return $user->hasAnyRole(['mentor', 'admin', 'super-admin', 'dawah-coordinator']);
    }

    /**
     * Load Dawah schedule.
     */
    public function index(Request $request): JsonResponse
    {
        $sessions = $this->readSchedule();
        return response()->json([
            'success' => true,
            'canEdit' => $this->canUserEdit($request),
            'sessions' => $sessions,
        ]);
    }

    /**
     * Update Dawah schedule.
     */
    public function update(Request $request): JsonResponse
    {
        if (!$this->canUserEdit($request)) {
            return response()->json([
                'success' => false,
                'error' => 'Only mentors and admins can edit the Dawah schedule.',
            ], 403);
        }

        $sessions = $request->input('sessions');
        if (!is_array($sessions) || empty($sessions)) {
            return response()->json([
                'success' => false,
                'error' => 'At least one schedule session is required.',
            ], 400);
        }

        $sanitized = [];
        foreach ($sessions as $index => $raw) {
            $fallback = $this->getDefaultSchedule()[$index % count($this->getDefaultSchedule())];
            $sanitized[] = [
                'id' => isset($raw['id']) && trim($raw['id']) ? trim($raw['id']) : uniqid('session-', true),
                'title' => isset($raw['title']) && trim($raw['title']) ? substr(trim($raw['title']), 0, 120) : $fallback['title'],
                'date' => isset($raw['date']) && trim($raw['date']) ? substr(trim($raw['date']), 0, 24) : $fallback['date'],
                'time' => isset($raw['time']) && trim($raw['time']) ? substr(trim($raw['time']), 0, 80) : $fallback['time'],
                'location' => isset($raw['location']) && trim($raw['location']) ? substr(trim($raw['location']), 0, 160) : $fallback['location'],
                'lead' => isset($raw['lead']) && trim($raw['lead']) ? substr(trim($raw['lead']), 0, 100) : $fallback['lead'],
                'focus' => isset($raw['focus']) && trim($raw['focus']) ? substr(trim($raw['focus']), 0, 180) : $fallback['focus'],
                'notes' => isset($raw['notes']) ? substr(trim($raw['notes']), 0, 400) : '',
            ];
        }

        $this->writeSchedule($sanitized);

        return response()->json([
            'success' => true,
            'canEdit' => true,
            'sessions' => $sanitized,
        ]);
    }
}
