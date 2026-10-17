<?php

namespace App\Http\Controllers\Api\V1\Support;

use App\Http\Controllers\Controller;
use App\Models\SupportInquiry;
use App\Models\SupportMessage;
use App\Models\User;
use App\Notifications\SupportReplyNotification;
use App\Notifications\SupportStatusUpdatedNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StaffSupportController extends Controller
{
    /**
     * Staff triage: list all member inquiries with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('manage', SupportInquiry::class);

        $query = SupportInquiry::with([
            'user:id,name,email,uuid',
            'assignedTo:id,name,uuid',
            'latestMessage',
        ])->orderBy('last_activity_at', 'desc');

        if ($request->filled('status')) {
            $status = $request->input('status');
            if ($status === 'active') {
                $query->whereNotIn('status', [SupportInquiry::STATUS_CLOSED, SupportInquiry::STATUS_RESOLVED]);
            } else {
                $query->where('status', $status);
            }
        }

        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        if ($request->filled('assigned_to')) {
            $assigned = $request->input('assigned_to');
            if ($assigned === 'me') {
                $query->where('assigned_to_user_id', $request->user()->id);
            } elseif ($assigned === 'unassigned') {
                $query->whereNull('assigned_to_user_id');
            } else {
                $query->where('assigned_to_user_id', $assigned);
            }
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('reference_code', 'like', "%{$search}%")
                  ->orWhere('subject', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('name', 'like', "%{$search}%")
                         ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        $inquiries = $query->paginate(20);

        return response()->json($inquiries);
    }

    /**
     * Staff view inquiry details and message history.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $this->authorize('manage', SupportInquiry::class);

        $inquiry = SupportInquiry::findByIdentifierOrFail($id);
        $inquiry->load([
            'user:id,name,email,uuid',
            'assignedTo:id,name,uuid',
            'messages.user:id,name,uuid',
        ]);

        // Mark member messages as read by staff if viewed
        SupportMessage::where('inquiry_id', $inquiry->id)
            ->where('is_staff_reply', false)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'inquiry' => $inquiry,
        ]);
    }

    /**
     * Staff post reply to member.
     */
    public function reply(Request $request, string $id): JsonResponse
    {
        $this->authorize('manage', SupportInquiry::class);

        $inquiry = SupportInquiry::findByIdentifierOrFail($id);

        $validated = $request->validate([
            'message' => 'required|string|min:1|max:10000',
        ]);

        return DB::transaction(function () use ($request, $inquiry, $validated) {
            $cleanMessage = trim(strip_tags($validated['message']));
            if (empty($cleanMessage)) {
                return response()->json([
                    'message' => 'Message cannot be empty or contain only whitespace.',
                ], 422);
            }

            $message = SupportMessage::create([
                'inquiry_id' => $inquiry->id,
                'user_id' => $request->user()->id,
                'is_staff_reply' => true,
                'message' => $cleanMessage,
                'read_at' => null, // Member has not read yet
            ]);

            // Auto-assign to replying staff if currently unassigned
            $assignedId = $inquiry->assigned_to_user_id ?: $request->user()->id;

            $inquiry->update([
                'status' => SupportInquiry::STATUS_AWAITING_MEMBER,
                'assigned_to_user_id' => $assignedId,
                'last_activity_at' => now(),
            ]);

            // Dispatch notification to member post-commit
            DB::afterCommit(function () use ($request, $inquiry, $message) {
                if ($inquiry->user && $inquiry->user_id !== $request->user()->id) {
                    try {
                        $inquiry->user->notify(new SupportReplyNotification($inquiry, $message));
                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::warning('Failed to dispatch support reply notification', [
                            'inquiry_id' => $inquiry->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

            $inquiry->load([
                'user:id,name,email,uuid',
                'assignedTo:id,name,uuid',
                'messages.user:id,name,uuid',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Staff response sent.',
                'data' => $message->load('user:id,name,uuid'),
                'inquiry' => $inquiry,
            ], 201);
        });
    }

    /**
     * Staff update inquiry status.
     */
    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $this->authorize('manage', SupportInquiry::class);

        $inquiry = SupportInquiry::findByIdentifierOrFail($id);

        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(SupportInquiry::VALID_STATUSES)],
        ]);

        return DB::transaction(function () use ($request, $inquiry, $validated) {
            $updates = [
                'status' => $validated['status'],
                'last_activity_at' => now(),
            ];

            if (in_array($validated['status'], [SupportInquiry::STATUS_CLOSED, SupportInquiry::STATUS_RESOLVED])) {
                $updates['closed_at'] = now();
            } else {
                $updates['closed_at'] = null;
            }

            $oldStatus = $inquiry->status;
            $newStatus = $validated['status'];
            $inquiry->update($updates);

            if ($oldStatus !== $newStatus && $inquiry->user && $inquiry->user_id !== $request->user()->id) {
                DB::afterCommit(function () use ($inquiry, $oldStatus, $newStatus) {
                    try {
                        $inquiry->user->notify(new SupportStatusUpdatedNotification($inquiry, $oldStatus, $newStatus));
                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::warning('Failed to dispatch support status notification', [
                            'inquiry_id' => $inquiry->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                });
            }

            $inquiry->load([
                'user:id,name,email,uuid',
                'assignedTo:id,name,uuid',
                'messages.user:id,name,uuid',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Inquiry status updated to ' . $newStatus . '.',
                'inquiry' => $inquiry,
            ]);
        });
    }

    /**
     * Staff assign inquiry to a team member.
     */
    public function assign(Request $request, string $id): JsonResponse
    {
        $this->authorize('manage', SupportInquiry::class);

        $inquiry = SupportInquiry::findByIdentifierOrFail($id);

        $validated = $request->validate([
            'assigned_to_user_id' => 'nullable|exists:users,id',
        ]);

        $inquiry->update([
            'assigned_to_user_id' => $validated['assigned_to_user_id'],
            'last_activity_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Inquiry assignment updated.',
            'inquiry' => $inquiry->load([
                'user:id,name,email,uuid',
                'assignedTo:id,name,uuid',
                'messages.user:id,name,uuid',
            ]),
        ]);
    }

    /**
     * Support overview stats for staff dashboard.
     */
    public function stats(Request $request): JsonResponse
    {
        $this->authorize('manage', SupportInquiry::class);

        $totalOpen = SupportInquiry::whereIn('status', [
            SupportInquiry::STATUS_OPEN,
            SupportInquiry::STATUS_AWAITING_STAFF,
        ])->count();

        $inReview = SupportInquiry::where('status', SupportInquiry::STATUS_IN_REVIEW)->count();
        $awaitingMember = SupportInquiry::where('status', SupportInquiry::STATUS_AWAITING_MEMBER)->count();
        $unassigned = SupportInquiry::whereNull('assigned_to_user_id')
            ->whereNotIn('status', [SupportInquiry::STATUS_CLOSED, SupportInquiry::STATUS_RESOLVED])
            ->count();

        return response()->json([
            'total_open' => $totalOpen,
            'in_review' => $inReview,
            'awaiting_member' => $awaitingMember,
            'unassigned' => $unassigned,
        ]);
    }
}
