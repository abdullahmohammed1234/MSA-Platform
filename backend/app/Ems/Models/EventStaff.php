<?php

namespace App\Ems\Models;

use App\Ems\Enums\EventTeamRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user staffed on an event.
 *
 * Phase 1 uses this purely for read scoping: staff see the events they are
 * assigned to. Phase 4 layers check-in shifts and stations on top.
 *
 * @property int $event_id
 * @property int $user_id
 * @property EventTeamRole $role
 */
class EventStaff extends Model
{
    protected $table = 'ems_event_staff';

    protected $fillable = [
        'event_id',
        'user_id',
        'role',
        'notes',
        'assigned_by',
    ];

    protected function casts(): array
    {
        return [
            'role' => EventTeamRole::class,
        ];
    }

    protected $attributes = [
        'role' => EventTeamRole::Staff->value,
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
