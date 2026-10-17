<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'user_one_id',
        'user_two_id',
        'last_message_at',
        'last_message_preview',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($conversation) {
            if (empty($conversation->uuid)) {
                $conversation->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Find a conversation by numeric ID or UUID.
     */
    public static function findByIdentifierOrFail(string|int $identifier): self
    {
        if (is_numeric($identifier)) {
            return static::where('id', $identifier)->firstOrFail();
        }

        return static::where('uuid', $identifier)->firstOrFail();
    }

    /**
     * Get the participants relationship.
     */
    public function participants(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class, 'conversation_id');
    }

    /**
     * Get the users in the conversation.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_participants', 'conversation_id', 'user_id')
            ->withPivot(['id', 'last_read_at'])
            ->withTimestamps();
    }

    /**
     * Get the messages in the conversation.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(DirectMessage::class, 'conversation_id')->orderBy('created_at', 'asc');
    }

    /**
     * User one relationship.
     */
    public function userOne(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_one_id');
    }

    /**
     * User two relationship.
     */
    public function userTwo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_two_id');
    }

    /**
     * Determine if a user is a participant.
     */
    public function isParticipant(User $user): bool
    {
        return $this->user_one_id === $user->id || $this->user_two_id === $user->id;
    }

    /**
     * Get the other participant in the conversation.
     */
    public function otherParticipant(User $user): ?User
    {
        if ($this->user_one_id === $user->id) {
            return $this->userTwo;
        }

        if ($this->user_two_id === $user->id) {
            return $this->userOne;
        }

        return null;
    }

    /**
     * Get the participant record for a specific user.
     */
    public function participantFor(User $user): ?ConversationParticipant
    {
        return $this->participants()->where('user_id', $user->id)->first();
    }

    /**
     * Calculate unread messages count for a specific user.
     */
    public function unreadCountFor(User $user): int
    {
        $participant = $this->participantFor($user);
        $lastReadAt = $participant?->last_read_at;

        $query = $this->messages()->where('sender_id', '!=', $user->id);

        if ($lastReadAt) {
            $query->where('created_at', '>', $lastReadAt);
        }

        return $query->count();
    }
}
