<?php

namespace App\Http\Controllers\Api\V1\Support;

use App\Http\Controllers\Controller;
use App\Models\SupportInquiry;
use App\Models\SupportMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SupportInquiryController extends Controller
{
    /**
     * Return allowed support categories.
     */
    public function categories(): JsonResponse
    {
        $descriptions = [
            'general' => 'General questions about SFU MSA activities and community',
            'new_muslim' => 'Welcoming support, learning materials, and peer connection for new Muslims',
            'faith_questions' => 'Questions regarding campus halaqahs, study circles, and prayers',
            'campus_prayer' => 'Prayer room access (Burnaby AQ 3200 & Surrey), Jummah schedules, and ablution facilities',
            'academic' => 'Jummah / Eid exam conflict letters and accommodation guidance',
            'event_support' => 'Questions regarding upcoming campus gatherings and registration',
            'feedback' => 'Ideas to improve SFU MSA services and student experience',
            'other' => 'Any other private inquiry not listed above',
        ];

        $categories = collect(SupportInquiry::CATEGORIES)->map(function ($name, $key) use ($descriptions) {
            return [
                'key' => $key,
                'name' => $name,
                'value' => $key,
                'label' => $name,
                'description' => $descriptions[$key] ?? 'Inquiry regarding ' . $name,
            ];
        })->values();

        return response()->json([
            'categories' => $categories,
        ]);
    }

    /**
     * Get member's own submitted inquiries.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = SupportInquiry::where('user_id', $user->id)
            ->with(['latestMessage'])
            ->orderBy('last_activity_at', 'desc');

        if ($request->filled('status')) {
            $status = $request->input('status');
            if ($status === 'active') {
                $query->whereNotIn('status', [SupportInquiry::STATUS_CLOSED, SupportInquiry::STATUS_RESOLVED]);
            } elseif ($status === 'closed') {
                $query->whereIn('status', [SupportInquiry::STATUS_CLOSED, SupportInquiry::STATUS_RESOLVED]);
            } else {
                $query->where('status', $status);
            }
        }

        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        $inquiries = $query->paginate(15);

        // Append unread staff message count per inquiry for member
        $inquiries->getCollection()->transform(function ($inquiry) {
            $unreadCount = SupportMessage::where('inquiry_id', $inquiry->id)
                ->where('is_staff_reply', true)
                ->whereNull('read_at')
                ->count();

            $inquiry->unread_replies_count = $unreadCount;
            return $inquiry;
        });

        return response()->json($inquiries);
    }

    /**
     * Submit a new support inquiry.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        // Support convenient aliases from various clients
        $aliases = [
            'revert_support' => 'new_muslim',
            'spiritual' => 'faith_questions',
            'events' => 'event_support',
        ];
        if ($request->has('category') && isset($aliases[$request->input('category')])) {
            $request->merge(['category' => $aliases[$request->input('category')]]);
        }

        $validated = $request->validate([
            'category' => ['required', 'string', Rule::in(array_keys(SupportInquiry::CATEGORIES))],
            'subject' => 'required|string|min:3|max:255',
            'message' => 'required|string|min:5|max:10000',
        ]);

        return DB::transaction(function () use ($user, $validated) {
            $cleanSubject = trim(strip_tags($validated['subject']));
            $cleanMessage = trim(strip_tags($validated['message']));

            if (empty($cleanSubject)) {
                return response()->json([
                    'message' => 'Subject cannot be empty or contain only whitespace.',
                ], 422);
            }

            if (empty($cleanMessage)) {
                return response()->json([
                    'message' => 'Message cannot be empty or contain only whitespace.',
                ], 422);
            }

            $inquiry = SupportInquiry::create([
                'user_id' => $user->id,
                'category' => $validated['category'],
                'subject' => $cleanSubject,
                'status' => SupportInquiry::STATUS_OPEN,
                'priority' => 'normal',
                'last_activity_at' => now(),
            ]);

            $message = SupportMessage::create([
                'inquiry_id' => $inquiry->id,
                'user_id' => $user->id,
                'is_staff_reply' => false,
                'message' => $cleanMessage,
                'read_at' => now(), // Member's own message is read by themselves
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Your inquiry has been submitted successfully. An MSA coordinator will follow up with you.',
                'inquiry' => $inquiry->load('latestMessage'),
            ], 201);
        });
    }

    /**
     * View an inquiry and its conversation history.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $inquiry = SupportInquiry::findByIdentifierOrFail($id);

        $this->authorize('view', $inquiry);

        // Mark unread staff messages as read if viewer is the member
        if ($request->user()->id === $inquiry->user_id) {
            SupportMessage::where('inquiry_id', $inquiry->id)
                ->where('is_staff_reply', true)
                ->whereNull('read_at')
                ->update(['read_at' => now()]);
        }

        $inquiry->load([
            'user:id,name,email,uuid',
            'assignedTo:id,name,uuid',
            'messages.user:id,name,uuid',
        ]);

        return response()->json([
            'inquiry' => $inquiry,
        ]);
    }

    /**
     * Send a follow-up message in an open inquiry.
     */
    public function sendMessage(Request $request, string $id): JsonResponse
    {
        $inquiry = SupportInquiry::findByIdentifierOrFail($id);

        $this->authorize('reply', $inquiry);

        if ($inquiry->isClosed()) {
            return response()->json([
                'message' => 'This inquiry is closed. Please submit a new inquiry for additional questions.',
            ], 422);
        }

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
                'is_staff_reply' => false,
                'message' => $cleanMessage,
                'read_at' => now(),
            ]);

            // If inquiry was awaiting member, transition to awaiting staff
            $newStatus = $inquiry->status;
            if ($inquiry->status === SupportInquiry::STATUS_AWAITING_MEMBER) {
                $newStatus = SupportInquiry::STATUS_AWAITING_STAFF;
            } elseif ($inquiry->status === SupportInquiry::STATUS_RESOLVED) {
                $newStatus = SupportInquiry::STATUS_OPEN;
            }

            $inquiry->update([
                'status' => $newStatus,
                'last_activity_at' => now(),
            ]);

            $inquiry->load([
                'user:id,name,email,uuid',
                'assignedTo:id,name,uuid',
                'messages.user:id,name,uuid',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Message sent.',
                'data' => $message->load('user:id,name,uuid'),
                'inquiry' => $inquiry,
            ], 201);
        });
    }

    /**
     * Member closes their inquiry.
     */
    public function close(Request $request, string $id): JsonResponse
    {
        $inquiry = SupportInquiry::findByIdentifierOrFail($id);

        $this->authorize('close', $inquiry);

        $inquiry->update([
            'status' => SupportInquiry::STATUS_CLOSED,
            'closed_at' => now(),
            'last_activity_at' => now(),
        ]);

        $inquiry->load([
            'user:id,name,email,uuid',
            'assignedTo:id,name,uuid',
            'messages.user:id,name,uuid',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Inquiry closed.',
            'inquiry' => $inquiry,
        ]);
    }

    /**
     * Check if the community resources feature is published.
     */
    public function resourcesStatus(): JsonResponse
    {
        $enabled = config('features.connect_resources_enabled', false);
        try {
            if (class_exists(\App\Http\Controllers\Api\V1\Admin\ConnectSystemController::class)) {
                $connectConfig = \App\Http\Controllers\Api\V1\Admin\ConnectSystemController::getPersistedConfig();
                if (isset($connectConfig['connect_resources_enabled'])) {
                    $enabled = (bool) $connectConfig['connect_resources_enabled'];
                }
            }
        } catch (\Throwable) {
        }

        return response()->json([
            'enabled' => $enabled,
        ]);
    }
}
