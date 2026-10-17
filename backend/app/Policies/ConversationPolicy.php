<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;
use App\Services\Messaging\DirectMessagingAuthorizationService;
use Illuminate\Auth\Access\HandlesAuthorization;

class ConversationPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view the conversation.
     */
    public function view(User $user, Conversation $conversation): bool
    {
        if (!$user->is_active || !$conversation->isParticipant($user)) {
            return false;
        }

        // Enforce the core invariant: conversation must have at least one administrator
        return DirectMessagingAuthorizationService::isAdmin($conversation->userOne)
            || DirectMessagingAuthorizationService::isAdmin($conversation->userTwo);
    }

    /**
     * Determine whether the user can send a message in the conversation.
     */
    public function reply(User $user, Conversation $conversation): bool
    {
        if (!$user->is_active || !$conversation->isParticipant($user)) {
            return false;
        }

        return DirectMessagingAuthorizationService::isAdmin($conversation->userOne)
            || DirectMessagingAuthorizationService::isAdmin($conversation->userTwo);
    }

    /**
     * Determine whether the user can mark the conversation as read.
     */
    public function markAsRead(User $user, Conversation $conversation): bool
    {
        if (!$user->is_active || !$conversation->isParticipant($user)) {
            return false;
        }

        return true;
    }
}
