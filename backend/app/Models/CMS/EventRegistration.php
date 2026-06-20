<?php

namespace App\Models\CMS;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class EventRegistration extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'event_id',
        'user_id',
        'first_name',
        'last_name',
        'email',
        'student_id',
        'status',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $registration) {
            if (empty($registration->uuid)) {
                $registration->uuid = (string) Str::uuid();
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
}
