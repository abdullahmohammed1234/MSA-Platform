<?php

namespace App\Ems\Models;

use App\Ems\Enums\CheckInMethod;
use App\Ems\Models\Concerns\HasEmsUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An attendance record for a single ticket redemption.
 *
 * Undo deletes this row after writing history to ems_check_in_audits so the
 * unique ticket_id index can accept a later re-check-in.
 */
class CheckIn extends Model
{
    use HasEmsUuid;

    protected $table = 'ems_check_ins';

    protected $fillable = [
        'uuid',
        'event_id',
        'ticket_id',
        'registration_id',
        'checked_in_by',
        'checked_in_at',
        'method',
        'device',
        'notes',
        'undone_at',
        'undone_by',
        'undo_reason',
    ];

    protected function casts(): array
    {
        return [
            'checked_in_at' => 'datetime',
            'undone_at' => 'datetime',
            'method' => CheckInMethod::class,
        ];
    }

    protected $attributes = [
        'method' => CheckInMethod::Manual->value,
    ];

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    /**
     * @return BelongsTo<Ticket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    /**
     * @return BelongsTo<Registration, $this>
     */
    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class, 'registration_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function checkedInBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_in_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function undoneBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'undone_by');
    }
}
