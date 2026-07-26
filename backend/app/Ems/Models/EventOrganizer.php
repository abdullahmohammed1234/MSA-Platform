<?php

namespace App\Ems\Models;

use App\Ems\Enums\EventTeamRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user assigned as a co-organizer of an event.
 *
 * The accountable owner is Event::organizer_id; this table carries everyone
 * else who may act on the event.
 *
 * @property int $event_id
 * @property int $user_id
 * @property EventTeamRole $role
 * @property bool $is_primary
 */
class EventOrganizer extends Model
{
    protected $table = 'ems_event_organizers';

    protected $fillable = [
        'event_id',
        'user_id',
        'role',
        'is_primary',
        'assigned_by',
    ];

    protected function casts(): array
    {
        return [
            'role' => EventTeamRole::class,
            'is_primary' => 'boolean',
        ];
    }

    protected $attributes = [
        'role' => EventTeamRole::CoOrganizer->value,
    ];

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
