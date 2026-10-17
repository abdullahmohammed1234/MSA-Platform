<?php

namespace App\Http\Controllers\Api\V1\Messaging;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\DirectMessage;
use App\Models\User;
use App\Notifications\DirectMessageNotification;
use App\Services\Messaging\DirectMessagingAuthorizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ConversationController extends Controller
{
    /**
     * List all conversations for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $conversations = Conversation::where(function ($query) use ($user) {
            $query->where('user_one_id', $user->id)
                  ->orWhere('user_two_id', $user->id);
        })
        ->with([
            'userOne:id,uuid,name,email,avatar,is_active',
            'userOne.roles:id,name,slug',
            'userTwo:id,uuid,name,email,avatar,is_active',
            'userTwo.roles:id,name,slug',
            'participants',
        ])
        ->orderByDesc('last_message_at')
        ->orderByDesc('created_at')
        ->paginate((int) $request->input('per_page', 20));

        $conversations->getCollection()->transform(function (Conversation $conv) use ($user) {
            return $this->formatConversation($conv, $user);
        });

        return response()->json($conversations);
    }

    /**
     * Create or retrieve a conversation between the authenticated user and a recipient.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'recipient_id' => 'required',
            'message' => 'nullable|string|max:5000',
        ]);

        $recipientIdentifier = $request->input('recipient_id');
        $recipient = is_numeric($recipientIdentifier)
            ? User::find($recipientIdentifier)
            : User::where('uuid', $recipientIdentifier)->first();

        if (!$recipient) {
            return response()->json(['message' => 'The selected recipient does not exist.'], 404);
        }

        // Validate the pair against core product invariants
        $validation = DirectMessagingAuthorizationService::validatePair($user, $recipient);
        if (!$validation['valid']) {
            return response()->json(['message' => $validation['reason']], $validation['status']);
        }

        $userOneId = min($user->id, $recipient->id);
        $userTwoId = max($user->id, $recipient->id);

        $createdMessage = null;

        $conversation = DB::transaction(function () use ($userOneId, $userTwoId, $user, $recipient, $request, &$createdMessage) {
            // Find or create conversation with concurrency safety via unique key
            $conv = Conversation::firstOrCreate([
                'user_one_id' => $userOneId,
                'user_two_id' => $userTwoId,
            ]);

            // Ensure participant rows exist
            ConversationParticipant::firstOrCreate([
                'conversation_id' => $conv->id,
                'user_id' => $user->id,
            ]);

            ConversationParticipant::firstOrCreate([
                'conversation_id' => $conv->id,
                'user_id' => $recipient->id,
            ]);

            // Handle optional initial message
            $rawBody = $request->input('message');
            if ($rawBody !== null) {
                $cleanBody = trim($rawBody);
                if ($cleanBody !== '') {
                    $createdMessage = DirectMessage::create([
                        'conversation_id' => $conv->id,
                        'sender_id' => $user->id,
                        'body' => $cleanBody,
                    ]);

                    $conv->update([
                        'last_message_at' => now(),
                        'last_message_preview' => Str::limit($cleanBody, 100),
                    ]);

                    $conv->participantFor($user)?->update(['last_read_at' => now()]);
                }
            }

            return $conv;
        });

        // Post-commit notification dispatch
        if ($createdMessage) {
            try {
                $recipient->notify(new DirectMessageNotification($conversation, $user));
            } catch (\Throwable $e) {
                Log::warning('DirectMessageNotification delivery failure', [
                    'correlation_id' => (string) Str::uuid(),
                    'conversation_id' => $conversation->id,
                    'recipient_id' => $recipient->id,
                    'error_classification' => class_basename($e),
                ]);
            }
        }

        $conversation->load([
            'userOne:id,uuid,name,email,avatar,is_active',
            'userOne.roles:id,name,slug',
            'userTwo:id,uuid,name,email,avatar,is_active',
            'userTwo.roles:id,name,slug',
            'participants',
        ]);

        return response()->json($this->formatConversation($conversation, $user), 201);
    }

    /**
     * Show a single conversation by ID or UUID.
     */
    public function show(Request $request, string $identifier): JsonResponse
    {
        $user = $request->user();
        $conversation = Conversation::findByIdentifierOrFail($identifier);

        $this->authorizeParticipant($conversation, $user);

        $conversation->load([
            'userOne:id,uuid,name,email,avatar,is_active',
            'userOne.roles:id,name,slug',
            'userTwo:id,uuid,name,email,avatar,is_active',
            'userTwo.roles:id,name,slug',
            'participants',
        ]);

        return response()->json($this->formatConversation($conversation, $user));
    }

    /**
     * Fetch message history for a conversation with bounded pagination.
     */
    public function messages(Request $request, string $identifier): JsonResponse
    {
        $user = $request->user();
        $conversation = Conversation::findByIdentifierOrFail($identifier);

        $this->authorizeParticipant($conversation, $user);

        $perPage = min(max((int) $request->input('per_page', 30), 1), 100);

        $messages = $conversation->messages()
            ->with(['sender:id,uuid,name,avatar'])
            ->orderBy('created_at', 'asc')
            ->paginate($perPage);

        $messages->getCollection()->transform(function (DirectMessage $msg) use ($user) {
            return [
                'id' => $msg->id,
                'uuid' => $msg->uuid,
                'conversation_id' => $msg->conversation_id,
                'sender_id' => $msg->sender_id,
                'is_mine' => $msg->sender_id === $user->id,
                'sender' => [
                    'id' => $msg->sender->id,
                    'uuid' => $msg->sender->uuid,
                    'name' => $msg->sender->name,
                    'avatar' => $msg->sender->avatar,
                ],
                'body' => $msg->body,
                'created_at' => $msg->created_at->toIso8601String(),
            ];
        });

        return response()->json($messages);
    }

    /**
     * Send a new message in a conversation.
     */
    public function sendMessage(Request $request, string $identifier): JsonResponse
    {
        $user = $request->user();
        $conversation = Conversation::findByIdentifierOrFail($identifier);

        $this->authorizeParticipant($conversation, $user);

        $request->validate([
            'body' => 'required|string|max:5000',
        ]);

        $cleanBody = trim($request->input('body'));
        if ($cleanBody === '') {
            return response()->json(['message' => 'Message body cannot be empty or whitespace only.'], 422);
        }

        $recipient = $conversation->otherParticipant($user);
        if (!$recipient || !$recipient->is_active) {
            return response()->json(['message' => 'The other participant in this conversation is inactive or ineligible.'], 422);
        }

        $message = DB::transaction(function () use ($conversation, $user, $cleanBody) {
            $msg = DirectMessage::create([
                'conversation_id' => $conversation->id,
                'sender_id' => $user->id,
                'body' => $cleanBody,
            ]);

            $conversation->update([
                'last_message_at' => now(),
                'last_message_preview' => Str::limit($cleanBody, 100),
            ]);

            $conversation->participantFor($user)?->update([
                'last_read_at' => now(),
            ]);

            return $msg;
        });

        // Post-commit notification dispatch to recipient
        try {
            $recipient->notify(new DirectMessageNotification($conversation, $user));
        } catch (\Throwable $e) {
            Log::warning('DirectMessageNotification delivery failure', [
                'correlation_id' => (string) Str::uuid(),
                'conversation_id' => $conversation->id,
                'recipient_id' => $recipient->id,
                'error_classification' => class_basename($e),
            ]);
        }

        $message->load(['sender:id,uuid,name,avatar']);

        return response()->json([
            'id' => $message->id,
            'uuid' => $message->uuid,
            'conversation_id' => $message->conversation_id,
            'sender_id' => $message->sender_id,
            'is_mine' => true,
            'sender' => [
                'id' => $message->sender->id,
                'uuid' => $message->sender->uuid,
                'name' => $message->sender->name,
                'avatar' => $message->sender->avatar,
            ],
            'body' => $message->body,
            'created_at' => $message->created_at->toIso8601String(),
        ], 201);
    }

    /**
     * Mark a conversation as read for the authenticated participant.
     */
    public function markAsRead(Request $request, string $identifier): JsonResponse
    {
        $user = $request->user();
        $conversation = Conversation::findByIdentifierOrFail($identifier);

        $this->authorizeParticipant($conversation, $user);

        $participant = $conversation->participantFor($user);
        if ($participant) {
            $targetTime = now();
            if ($request->filled('message_id')) {
                $targetMessage = $conversation->messages()->where('id', $request->input('message_id'))->first();
                if ($targetMessage) {
                    $targetTime = $targetMessage->created_at;
                }
            }

            // Ensure read cursor only advances monotonically forward,
            // preventing race conditions or stale client requests from moving the cursor backwards.
            DB::table('conversation_participants')
                ->where('id', $participant->id)
                ->where(function ($q) use ($targetTime) {
                    $q->whereNull('last_read_at')
                      ->orWhere('last_read_at', '<', $targetTime);
                })
                ->update([
                    'last_read_at' => $targetTime,
                    'updated_at' => now(),
                ]);

            $participant->refresh();
        }

        return response()->json([
            'success' => true,
            'read_at' => $participant?->last_read_at?->toIso8601String() ?? now()->toIso8601String(),
            'unread_count' => $conversation->unreadCountFor($user),
        ]);
    }

    /**
     * Get the total unread direct message count for the authenticated user.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $user = $request->user();

        // Count messages sent by other participants after user's last_read_at across all user's conversations
        $unreadCount = DB::table('conversation_participants as cp')
            ->join('conversations as c', 'c.id', '=', 'cp.conversation_id')
            ->join('direct_messages as m', 'm.conversation_id', '=', 'c.id')
            ->where('cp.user_id', $user->id)
            ->where('m.sender_id', '!=', $user->id)
            ->where(function ($query) {
                $query->whereNull('cp.last_read_at')
                      ->orWhereRaw('m.created_at > cp.last_read_at');
            })
            ->count();

        return response()->json([
            'unread_count' => $unreadCount,
        ]);
    }

    /**
     * List available administrators that members can reach out to.
     */
    public function availableAdmins(Request $request): JsonResponse
    {
        $admins = DirectMessagingAuthorizationService::getAvailableAdmins($request->user());

        return response()->json([
            'data' => $admins,
        ]);
    }

    /**
     * Helper to verify participant membership and the central administrator invariant.
     */
    private function authorizeParticipant(Conversation $conversation, User $user): void
    {
        if (!$conversation->isParticipant($user)) {
            abort(403, 'You are not a participant in this conversation.');
        }

        $validation = DirectMessagingAuthorizationService::validatePair($conversation->userOne, $conversation->userTwo);
        if (!$validation['valid']) {
            abort(403, $validation['reason'] ?? 'Unauthorized conversation access.');
        }
    }

    /**
     * Format a conversation for API output.
     */
    private function formatConversation(Conversation $conversation, User $currentUser): array
    {
        $otherUser = $conversation->otherParticipant($currentUser);
        $unreadCount = $conversation->unreadCountFor($currentUser);

        $roleLabel = 'Member';
        if ($otherUser && DirectMessagingAuthorizationService::isAdmin($otherUser)) {
            $roleLabel = $otherUser->roles->first()?->name ?? 'MSA Administrator';
        }

        return [
            'id' => $conversation->id,
            'uuid' => $conversation->uuid,
            'last_message_at' => $conversation->last_message_at?->toIso8601String(),
            'last_message_preview' => $conversation->last_message_preview,
            'unread_count' => $unreadCount,
            'created_at' => $conversation->created_at->toIso8601String(),
            'other_participant' => $otherUser ? [
                'id' => $otherUser->id,
                'uuid' => $otherUser->uuid,
                'name' => $otherUser->name,
                'avatar' => $otherUser->avatar,
                'role_label' => $roleLabel,
                'is_admin' => DirectMessagingAuthorizationService::isAdmin($otherUser),
            ] : null,
        ];
    }
}
