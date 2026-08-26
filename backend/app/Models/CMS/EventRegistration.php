<?php

namespace App\Models\CMS;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @deprecated Phase 9 — Legacy CMS RSVP table retained for archival only.
 * Application code must not use this model. EMS owns registrations/tickets/check-ins.
 */
class EventRegistration extends Model
{
    use HasFactory;

    public const STATUS_REGISTERED = 'registered';

    public const STATUS_ATTENDING = 'attending';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'uuid',
        'registration_group_id',
        'event_id',
        'user_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'student_id',
        'status',
        'checked_in_at',
    ];

    protected $casts = [
        'checked_in_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $registration) {
            if (empty($registration->uuid)) {
                $registration->uuid = (string) Str::uuid();
            }

            if (
                \Illuminate\Support\Facades\Schema::hasColumn('event_registrations', 'registration_group_id')
                && empty($registration->registration_group_id)
            ) {
                $registration->registration_group_id = (string) Str::uuid();
            }

            if (empty($registration->status)) {
                $registration->status = self::STATUS_REGISTERED;
            }

            $registration->email = strtolower($registration->email);
        });
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function markAttending(): void
    {
        $attributes = [
            'status' => self::STATUS_ATTENDING,
        ];

        if (\Illuminate\Support\Facades\Schema::hasColumn('event_registrations', 'checked_in_at')) {
            $attributes['checked_in_at'] = now();
        }

        $this->forceFill($attributes)->save();
    }
}
