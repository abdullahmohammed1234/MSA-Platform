<?php

namespace App\Mlibms\Models;

use App\Mlibms\Enums\ReservationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Reservation extends Model
{
    use HasFactory;

    protected $table = 'mlibms_reservations';

    protected $fillable = [
        'uuid',
        'book_id',
        'copy_id',
        'member_id',
        'status',
        'queue_position',
        'reserved_at',
        'ready_at',
        'expires_at',
        'fulfilled_at',
        'cancelled_at',
    ];

    protected $casts = [
        'status' => ReservationStatus::class,
        'queue_position' => 'integer',
        'reserved_at' => 'datetime',
        'ready_at' => 'datetime',
        'expires_at' => 'datetime',
        'fulfilled_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Reservation $reservation) {
            if (empty($reservation->uuid)) {
                $reservation->uuid = (string) Str::uuid();
            }
        });
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class, 'book_id');
    }

    public function copy(): BelongsTo
    {
        return $this->belongsTo(Copy::class, 'copy_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id');
    }
}
