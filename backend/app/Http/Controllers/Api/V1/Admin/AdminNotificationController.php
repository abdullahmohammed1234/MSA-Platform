<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\NotificationLog;
use App\Models\User;
use App\Events\AnnouncementPublishedEvent;
use App\Notifications\PlatformNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AdminNotificationController extends Controller
{
    /**
     * Get paginated notification delivery logs.
     */
    public function logs(Request $request)
    {
        $this->authorize('manage', Notification::class);

        $query = NotificationLog::with('user:id,name,email');

        if ($request->has('status') && !empty($request->status)) {
            $query->where('status', $request->status);
        }

        if ($request->has('channel') && !empty($request->channel)) {
            $query->where('channel', $request->channel);
        }

        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            })->orWhere('notification_type', 'like', "%{$search}%");
        }

        return response()->json($query->latest()->paginate(15));
    }

    /**
     * Resend a failed notification.
     */
    public function resend(Request $request, $logId)
    {
        $this->authorize('send', Notification::class);

        $log = NotificationLog::findOrFail($logId);

        if (! NotificationLog::claimForResend($log->id)) {
            return response()->json([
                'success' => false,
                'message' => 'Only failed notifications can be resent, and this one is already sent or being resent.'
            ], 409);
        }

        $log->refresh();

        $user = $log->user;
        if (!$user) {
            $log->update(['status' => 'failed']);

            return response()->json([
                'success' => false,
                'message' => 'User associated with this log no longer exists.'
            ], 422);
        }

        // Resend notification.
        // For security and simplicity, we instantiate a general PlatformNotification
        // with the original details or fallback properties.
        $title = 'Resent Notification';
        $message = 'This is a resent message from your administrator.';
        
        // Try to identify from class name
        $shortName = class_basename($log->notification_type);
        if ($shortName === 'CourseCompletedNotification') {
            $title = 'Course Completion';
            $message = 'Your course completion record has been re-dispatched.';
        } elseif ($shortName === 'CertificateEarnedNotification') {
            $title = 'Certificate Issued';
            $message = 'Your earned certificate notification has been re-dispatched.';
        } elseif ($shortName === 'NewAnnouncementNotification') {
            $title = 'Announcement Re-dispatch';
            $message = 'A published announcement has been re-dispatched to you.';
        } elseif ($shortName === 'UpcomingTrainingNotification') {
            $title = 'Training Session Reminder';
            $message = 'Your upcoming training reminder has been re-dispatched.';
        }

        try {
            // We dispatch it as a PlatformNotification which uses standard queueing
            $user->notify(new PlatformNotification($title, $message, [
                'original_type' => $log->notification_type,
                'resent' => true,
                'resent_at' => now()->toIso8601String()
            ]));
        } catch (\Throwable $e) {
            $log->update(['status' => 'failed']);
            Log::warning('admin.notifications.resend_failed', [
                'notification_log_id' => $log->id,
                'exception' => $e::class,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to resend notification. Please try again later.'
            ], 500);
        }

        // Superseded, not delivered: the new attempt's outcome is logged as its own row.
        $log->update(['status' => 'resent']);

        return response()->json([
            'success' => true,
            'message' => 'Notification re-queued for delivery.'
        ]);
    }

    /**
     * Broadcast a manual announcement notification.
     */
    public function broadcast(Request $request)
    {
        $this->authorize('send', Notification::class);

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'audience' => 'required|string|in:All,Volunteers,Mentors,Coordinators',
        ]);

        $slug = Str::slug($data['title']);

        // Publish event (this distributes notifications asynchronously via queue)
        event(new AnnouncementPublishedEvent(
            $data['title'],
            Str::limit($data['message'], 150),
            $slug,
            $data['audience'],
            null,
            $request->user()?->id,
        ));

        return response()->json([
            'success' => true,
            'message' => 'Announcement broadcast queued for ' . $data['audience'] . '.'
        ]);
    }

    /**
     * Get notification analytics and failure rates.
     */
    public function stats(Request $request)
    {
        $this->authorize('manage', Notification::class);

        $totalSent = NotificationLog::count();
        $totalFailed = NotificationLog::where('status', 'failed')->count();
        $inAppSent = NotificationLog::where('channel', 'in_app')->count();
        $emailSent = NotificationLog::where('channel', 'email')->count();

        // In-app tracking details
        $totalDb = Notification::count();
        $readDb = Notification::read()->count();
        $unreadDb = Notification::unread()->count();

        $readRate = $totalDb > 0 ? round(($readDb / $totalDb) * 100, 2) : 0;
        $failureRate = $totalSent > 0 ? round(($totalFailed / $totalSent) * 100, 2) : 0;

        return response()->json([
            'total_sent' => $totalSent,
            'total_failed' => $totalFailed,
            'in_app_sent' => $inAppSent,
            'email_sent' => $emailSent,
            'read_rate' => $readRate,
            'failure_rate' => $failureRate,
            'unread_count' => $unreadDb,
        ]);
    }
}
