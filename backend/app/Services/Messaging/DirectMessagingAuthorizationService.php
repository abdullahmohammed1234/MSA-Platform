<?php

namespace App\Services\Messaging;

use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DirectMessagingAuthorizationService
{
    /**
     * Roles qualifying as an authorized administrator for Direct Messaging.
     */
    public const ADMIN_ROLES = [
        'super-admin',
        'admin',
        'director',
        'dawah-coordinator',
        'ems-administrator',
        'store-administrator',
        'dms-administrator',
        'spms-administrator',
        'library-admin',
        'vms-administrator',
        'volunteer-administrator',
    ];

    /**
     * Permissions qualifying as an authorized administrator for Direct Messaging.
     * Note: Broad platform or infrastructure permissions (e.g. platform.operations, system.view)
     * are explicitly excluded to prevent privilege confusion.
     */
    public const ADMIN_PERMISSIONS = [
        'manage_users',
        'manage_support',
    ];

    /**
     * Recency window in minutes for suppressing emails when a recipient is actively reading.
     */
    public const RECENT_READ_WINDOW_MINUTES = 5;

    /**
     * Cooldown window in minutes between emails sent for the same conversation and recipient.
     */
    public const EMAIL_COOLDOWN_MINUTES = 5;

    /**
     * Determine if a user qualifies as an authorized administrator for Direct Messaging.
     */
    public static function isAdmin(?User $user): bool
    {
        if (!$user || !$user->is_active) {
            return false;
        }

        if ($user->hasAnyRole(self::ADMIN_ROLES)) {
            return true;
        }

        foreach (self::ADMIN_PERMISSIONS as $permission) {
            if ($user->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if a user is eligible to participate in conversations.
     */
    public static function isEligibleParticipant(?User $user): bool
    {
        if (!$user || !$user->is_active) {
            return false;
        }

        return true;
    }

    /**
     * Validate whether a conversation between two users satisfies all product invariants.
     *
     * Invariants:
     * 1. Exactly two distinct participants.
     * 2. Both participants must be active and eligible.
     * 3. At least one participant must be an authorized MSA administrator (isAdmin(A) OR isAdmin(B)).
     *    Member-to-member messaging is strictly prohibited.
     *
     * @return array{valid: bool, status: int, reason: string|null}
     */
    public static function validatePair(User $userA, User $userB): array
    {
        if ($userA->id === $userB->id) {
            return [
                'valid' => false,
                'status' => 422,
                'reason' => 'You cannot start a conversation with yourself.',
            ];
        }

        if (!self::isEligibleParticipant($userA) || !self::isEligibleParticipant($userB)) {
            return [
                'valid' => false,
                'status' => 422,
                'reason' => 'One or both selected accounts are deactivated or ineligible.',
            ];
        }

        $adminA = self::isAdmin($userA);
        $adminB = self::isAdmin($userB);

        if (!$adminA && !$adminB) {
            return [
                'valid' => false,
                'status' => 403,
                'reason' => 'Direct messaging is strictly reserved for conversations with an authorized MSA administrator. Member-to-member conversations are prohibited.',
            ];
        }

        return [
            'valid' => true,
            'status' => 200,
            'reason' => null,
        ];
    }

    /**
     * Return eligible administrators that members can contact.
     */
    public static function getAvailableAdmins(?User $excludeUser = null): Collection
    {
        $query = User::where('is_active', true)
            ->where(function (Builder $query) {
                $query->whereHas('roles', function (Builder $rq) {
                    $rq->whereIn('slug', self::ADMIN_ROLES);
                });
            });

        if ($excludeUser) {
            $query->where('id', '!=', $excludeUser->id);
        }

        return $query->select(['id', 'uuid', 'name', 'email', 'avatar'])
            ->with(['roles:id,name,slug'])
            ->orderBy('name', 'asc')
            ->get()
            ->map(function (User $user) {
                return [
                    'id' => $user->id,
                    'uuid' => $user->uuid,
                    'name' => $user->name,
                    'role_label' => $user->roles->first()?->name ?? 'MSA Administrator',
                    'avatar' => $user->avatar,
                ];
            });
    }

    /**
     * For administrators: search eligible members or other administrators.
     */
    public static function searchRecipients(User $admin, string $type, ?string $search, int $perPage = 15)
    {
        $query = User::where('is_active', true)
            ->where('id', '!=', $admin->id);

        if ($type === 'admins') {
            $query->whereHas('roles', function (Builder $rq) {
                $rq->whereIn('slug', self::ADMIN_ROLES);
            });
        } elseif ($type === 'members') {
            // Exclude staff roles to show ordinary community members
            $query->whereDoesntHave('roles', function (Builder $rq) {
                $rq->whereIn('slug', self::ADMIN_ROLES);
            });
        }

        if (!empty($search)) {
            $cleanSearch = trim($search);
            $query->where(function (Builder $q) use ($cleanSearch) {
                $q->where('name', 'like', "%{$cleanSearch}%")
                  ->orWhere('email', 'like', "%{$cleanSearch}%");
            });
        }

        return $query->select(['id', 'uuid', 'name', 'email', 'avatar'])
            ->with(['roles:id,name,slug'])
            ->orderBy('name', 'asc')
            ->paginate($perPage);
    }

    /**
     * Determine if a recipient has read the conversation within the recency window.
     * Note: Without live presence tracking, last_read_at indicates recent reading activity,
     * not definite proof that the user is currently viewing the active window.
     */
    public static function isRecentlyRead(
        Conversation $conversation,
        User $recipient,
        int $recencyMinutes = self::RECENT_READ_WINDOW_MINUTES
    ): bool {
        $participant = $conversation->participantFor($recipient);
        if (!$participant || !$participant->last_read_at) {
            return false;
        }

        return $participant->last_read_at->greaterThan(now()->subMinutes($recencyMinutes));
    }

    /**
     * Determine if direct message email notifications are currently throttled for this conversation.
     */
    public static function isEmailThrottled(
        Conversation $conversation,
        User $recipient,
        int $cooldownMinutes = self::EMAIL_COOLDOWN_MINUTES
    ): bool {
        $participant = $conversation->participantFor($recipient);
        if (!$participant || !$participant->last_email_notified_at) {
            return false;
        }

        return $participant->last_email_notified_at->greaterThan(now()->subMinutes($cooldownMinutes));
    }

    /**
     * Atomically claim an email notification for a recipient in a conversation.
     *
     * Ensures:
     * 1. The recipient has not read the conversation within $recencyMinutes.
     * 2. An email notification has not already been sent within $cooldownMinutes.
     * 3. Concurrent requests are serialized safely via database row locking.
     *
     * @return bool True if the claim was acquired and email delivery is permitted; false if suppressed.
     */
    public static function claimEmailNotification(
        Conversation $conversation,
        User $recipient,
        int $recencyMinutes = self::RECENT_READ_WINDOW_MINUTES,
        int $cooldownMinutes = self::EMAIL_COOLDOWN_MINUTES,
        ?\DateTimeInterface &$claimedAt = null
    ): bool {
        // Ensure participant record exists
        ConversationParticipant::firstOrCreate([
            'conversation_id' => $conversation->id,
            'user_id' => $recipient->id,
        ]);

        $now = now();
        $recencyCutoff = $now->copy()->subMinutes($recencyMinutes);
        $cooldownCutoff = $now->copy()->subMinutes($cooldownMinutes);

        // Atomic conditional update on the participant record.
        // In MySQL/InnoDB, this update takes an exclusive row lock on the unique index.
        // Concurrent requests for the same conversation and recipient are serialized.
        $affected = DB::table('conversation_participants')
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $recipient->id)
            ->where(function ($query) use ($recencyCutoff) {
                $query->whereNull('last_read_at')
                      ->orWhere('last_read_at', '<=', $recencyCutoff);
            })
            ->where(function ($query) use ($cooldownCutoff) {
                $query->whereNull('last_email_notified_at')
                      ->orWhere('last_email_notified_at', '<=', $cooldownCutoff);
            })
            ->update([
                'last_email_notified_at' => $now,
                'updated_at' => $now,
            ]);

        if ($affected > 0) {
            $claimedAt = $now;
            return true;
        }

        return false;
    }

    /**
     * Release an email notification claim if explicit rollback is needed.
     * When $claimedAt is provided, only releases the claim if last_email_notified_at
     * exactly matches $claimedAt, preventing accidental clearing of a newer claim
     * acquired by a concurrent request.
     */
    public static function releaseEmailNotificationClaim(
        Conversation $conversation,
        User $recipient,
        ?\DateTimeInterface $claimedAt = null
    ): bool {
        $query = DB::table('conversation_participants')
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $recipient->id);

        if ($claimedAt !== null) {
            $query->where('last_email_notified_at', $claimedAt);
        }

        return $query->update(['last_email_notified_at' => null]) > 0;
    }
}
