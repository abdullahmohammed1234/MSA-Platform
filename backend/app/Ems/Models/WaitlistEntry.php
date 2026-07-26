<?php

namespace App\Ems\Models;

use App\Ems\Enums\WaitlistStatus;
use App\Ems\Models\Concerns\HasEmsUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class WaitlistEntry extends Model
{
    use HasEmsUuid, SoftDeletes;

    protected $table = 'ems_waitlist_entries';

    protected $fillable = [
        'uuid',
        'event_id',
        'ticket_type_id',
        'user_id',
        'registration_id',
        'attendee_name',
        'attendee_email',
        'attendee_phone',
        'position',
        'quantity',
        'status',
        'notified_at',
        'promoted_at',
        'left_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'quantity' => 'integer',
            'status' => WaitlistStatus::class,
            'notified_at' => 'datetime',
            'promoted_at' => 'datetime',
            'left_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    protected $attributes = [
        'status' => WaitlistStatus::Waiting->value,
    ];

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    /**
     * @return BelongsTo<TicketType, $this>
     */
    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class, 'ticket_type_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<Registration, $this>
     */
    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class, 'registration_id');
    }

    /**
     * @param  Builder<WaitlistEntry>  $query
     * @return Builder<WaitlistEntry>
     */
    public function scopeWaiting(Builder $query): Builder
    {
        return $query->where('status', WaitlistStatus::Waiting->value)
            ->orderBy('position');
    }
}
