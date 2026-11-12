<?php

namespace App\Pm\Models;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PmUserCapacity extends Model
{
    use HasFactory;

    protected $table = 'pm_user_capacities';

    protected $fillable = [
        'user_id',
        'weekly_capacity_hours',
        'daily_capacity_hours',
        'daily_availability',
        'max_concurrent_tasks',
        'effective_from',
        'effective_to',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'weekly_capacity_hours' => 'decimal:2',
        'daily_capacity_hours' => 'decimal:2',
        'daily_availability' => 'array',
        'max_concurrent_tasks' => 'integer',
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Check if this capacity record is active on a given date.
     */
    public function isEffectiveOn(Carbon|string $date): bool
    {
        $d = Carbon::parse($date)->toDateString();

        if ($this->effective_from && $d < $this->effective_from->toDateString()) {
            return false;
        }

        if ($this->effective_to && $d > $this->effective_to->toDateString()) {
            return false;
        }

        return true;
    }

    /**
     * Get capacity hours for a specific day of the week (1 = Monday ... 7 = Sunday).
     */
    public function getDailyCapacityForDay(int $dayOfWeek): float
    {
        $dayMap = [
            1 => 'mon',
            2 => 'tue',
            3 => 'wed',
            4 => 'thu',
            5 => 'fri',
            6 => 'sat',
            7 => 'sun',
        ];

        $key = $dayMap[$dayOfWeek] ?? null;

        if ($this->daily_availability && is_array($this->daily_availability)) {
            if ($key && array_key_exists($key, $this->daily_availability)) {
                return (float) $this->daily_availability[$key];
            }
            if (array_key_exists((string) $dayOfWeek, $this->daily_availability)) {
                return (float) $this->daily_availability[(string) $dayOfWeek];
            }
        }

        // Default: Weekdays (1..5) receive daily_capacity_hours; weekends (6..7) receive 0.0
        return ($dayOfWeek >= 1 && $dayOfWeek <= 5) ? (float) $this->daily_capacity_hours : 0.0;
    }
}

