<?php

namespace App\Ems\Models;

use App\Ems\Enums\ReminderAudience;
use App\Ems\Enums\ReminderOffsetUnit;
use App\Ems\Models\Concerns\HasEmsUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class EventReminder extends Model
{
    use HasEmsUuid;

    protected $table = 'ems_event_reminders';

    protected $fillable = [
        'uuid',
        'event_id',
        'label',
        'offset_value',
        'offset_unit',
        'enabled',
        'template_key',
        'audience',
        'next_run_at',
        'last_run_at',
    ];

    protected function casts(): array
    {
        return [
            'offset_value' => 'integer',
            'offset_unit' => ReminderOffsetUnit::class,
            'enabled' => 'boolean',
            'audience' => ReminderAudience::class,
            'next_run_at' => 'datetime',
            'last_run_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    /**
     * @return HasMany<ReminderDispatch, $this>
     */
    public function dispatches(): HasMany
    {
        return $this->hasMany(ReminderDispatch::class, 'reminder_id');
    }

    /**
     * @param  Builder<EventReminder>  $query
     * @return Builder<EventReminder>
     */
    public function scopeDue(Builder $query): Builder
    {
        return $query
            ->where('enabled', true)
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', now());
    }

    public function computeNextRunAt(?Carbon $eventStart = null): ?Carbon
    {
        $start = $eventStart ?? $this->event?->start_at;

        if ($start === null) {
            return null;
        }

        $minutes = $this->offset_unit->toMinutes($this->offset_value);

        return $start->copy()->subMinutes($minutes);
    }

    public function displayLabel(): string
    {
        if (filled($this->label)) {
            return (string) $this->label;
        }

        return sprintf('%d %s before', $this->offset_value, $this->offset_unit->value);
    }
}
