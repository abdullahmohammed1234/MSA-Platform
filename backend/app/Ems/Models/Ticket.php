<?php

namespace App\Ems\Models;

use App\Ems\Enums\TicketStatus;
use App\Ems\Models\Concerns\HasEmsUuid;
use Database\Factories\Ems\TicketFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A ticket issued against a registration.
 *
 * @property string $code
 * @property int $event_id
 * @property int $registration_id
 * @property TicketStatus $status
 */
class Ticket extends Model
{
    /** @use HasFactory<TicketFactory> */
    use HasEmsUuid, HasFactory, SoftDeletes;

    protected $table = 'ems_tickets';

    protected $fillable = [
        'uuid',
        'code',
        'event_id',
        'registration_id',
        'ticket_type_id',
        'qr_payload',
        'qr_generated_at',
        'status',
        'holder_name',
        'holder_email',
        'issued_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => TicketStatus::class,
            'qr_generated_at' => 'datetime',
            'issued_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    protected $attributes = [
        'status' => TicketStatus::Issued->value,
    ];

    protected static function newFactory(): TicketFactory
    {
        return TicketFactory::new();
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
     * @return BelongsTo<TicketType, $this>
     */
    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class, 'ticket_type_id');
    }

    /**
     * A ticket can only be redeemed once; the database enforces it with a
     * unique index on ems_check_ins.ticket_id.
     *
     * @return HasOne<CheckIn, $this>
     */
    public function checkIn(): HasOne
    {
        return $this->hasOne(CheckIn::class, 'ticket_id');
    }

    public function isRedeemable(): bool
    {
        return $this->status->isRedeemable();
    }
}
