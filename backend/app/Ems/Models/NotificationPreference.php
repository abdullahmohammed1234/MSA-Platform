<?php

namespace App\Ems\Models;

use App\Ems\Models\Concerns\HasEmsUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends Model
{
    use HasEmsUuid;

    protected $table = 'ems_notification_preferences';

    protected $fillable = [
        'uuid',
        'user_id',
        'email',
        'event_reminders',
        'event_updates',
        'feedback_requests',
        'marketing_emails',
        'post_event',
    ];

    protected function casts(): array
    {
        return [
            'event_reminders' => 'boolean',
            'event_updates' => 'boolean',
            'feedback_requests' => 'boolean',
            'marketing_emails' => 'boolean',
            'post_event' => 'boolean',
        ];
    }

    protected $attributes = [
        'event_reminders' => true,
        'event_updates' => true,
        'feedback_requests' => true,
        'marketing_emails' => false,
        'post_event' => true,
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function allows(string $preferenceKey): bool
    {
        return (bool) ($this->{$preferenceKey} ?? true);
    }
}
