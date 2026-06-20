<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Get paginated notifications for the authenticated user.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $query = $user->customNotifications();

        if ($request->has('unread') && filter_var($request->unread, FILTER_VALIDATE_BOOLEAN)) {
            $query->unread();
        } elseif ($request->has('read') && filter_var($request->read, FILTER_VALIDATE_BOOLEAN)) {
            $query->read();
        }

        if ($request->has('category') && !empty($request->category)) {
            $query->where(function ($q) use ($request) {
                $q->where('type', 'like', '%' . $request->category . '%')
                  ->orWhere('data->type', $request->category);
            });
        }

        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('message', 'like', "%{$search}%");
            });
        }

        return response()->json($query->paginate(15));
    }

    /**
     * Get unread notification counts and latest unread list.
     */
    public function unread(Request $request)
    {
        $user = $request->user();
        $unreadQuery = $user->customNotifications()->unread();
        $unreadCount = (clone $unreadQuery)->count();
        $latestUnread = $unreadQuery->limit(5)->get();

        return response()->json([
            'unread_count' => $unreadCount,
            'latest_unread' => $latestUnread,
        ]);
    }

    /**
     * Mark a single notification as read.
     */
    public function read(Request $request, $id)
    {
        $notification = Notification::where('uuid', $id)->orWhere('id', $id)->firstOrFail();
        
        $this->authorize('update', $notification);

        $notification->markAsRead();

        return response()->json([
            'success' => true,
            'notification' => $notification
        ]);
    }

    /**
     * Mark all user's notifications as read.
     */
    public function readAll(Request $request)
    {
        $user = $request->user();
        $user->customNotifications()->unread()->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'All notifications marked as read.'
        ]);
    }

    /**
     * Delete a single notification.
     */
    public function destroy(Request $request, $id)
    {
        $notification = Notification::where('uuid', $id)->orWhere('id', $id)->firstOrFail();

        $this->authorize('delete', $notification);

        $notification->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notification deleted.'
        ]);
    }

    /**
     * Get user notification preferences.
     */
    public function getPreferences(Request $request)
    {
        $user = $request->user();
        
        $preferences = $user->notificationPreferences;

        if (!$preferences) {
            $preferences = $user->notificationPreferences()->create([
                'course_completion' => true,
                'new_announcements' => true,
                'upcoming_training' => true,
                'certificate_earned' => true,
                'email_enabled' => true,
                'in_app_enabled' => true,
            ]);
        }

        return response()->json($preferences);
    }

    /**
     * Update user notification preferences.
     */
    public function updatePreferences(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'course_completion' => 'sometimes|boolean',
            'new_announcements' => 'sometimes|boolean',
            'upcoming_training' => 'sometimes|boolean',
            'certificate_earned' => 'sometimes|boolean',
            'email_enabled' => 'sometimes|boolean',
            'in_app_enabled' => 'sometimes|boolean',
        ]);

        $preferences = $user->notificationPreferences;

        if (!$preferences) {
            $preferences = $user->notificationPreferences()->create([
                'course_completion' => true,
                'new_announcements' => true,
                'upcoming_training' => true,
                'certificate_earned' => true,
                'email_enabled' => true,
                'in_app_enabled' => true,
            ]);
        }

        $preferences->update($data);

        return response()->json([
            'success' => true,
            'preferences' => $preferences
        ]);
    }
}
