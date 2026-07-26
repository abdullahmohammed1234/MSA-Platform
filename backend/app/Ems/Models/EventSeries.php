<?php

namespace App\Ems\Models;

use App\Ems\Models\Concerns\HasEmsUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventSeries extends Model
{
    use HasEmsUuid, HasFactory;

    protected $table = 'ems_event_series';

    protected $fillable = [
        'uuid',
        'name',
        'description',
        'recurrence_pattern',
        'recurrence_interval',
        'recurrence_days',
        'start_date',
        'end_date',
        'created_by',
    ];

    protected $casts = [
        'recurrence_interval' => 'integer',
        'recurrence_days' => 'array',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function events(): HasMany
    {
        return $this->hasMany(Event::class, 'series_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
