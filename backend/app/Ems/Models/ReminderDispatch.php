<?php

namespace App\Ems\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReminderDispatch extends Model
{
    protected $table = 'ems_reminder_dispatches';

    protected $fillable = [
        'reminder_id',
        'event_id',
        'registration_id',
        'notification_id',
        'dispatched_at',
    ];

    protected function casts(): array
    {
        return [
            'dispatched_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<EventReminder, $this>
     */
    public function reminder(): BelongsTo
    {
        return $this->belongsTo(EventReminder::class, 'reminder_id');
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    /**
     * @return BelongsTo<Registration, $this>
     */
    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class, 'registration_id');
    }

    /**
     * @return BelongsTo<EventNotification, $this>
     */
    public function notification(): BelongsTo
    {
        return $this->belongsTo(EventNotification::class, 'notification_id');
    }
}
