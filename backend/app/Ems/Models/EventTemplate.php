<?php

namespace App\Ems\Models;

use App\Ems\Models\Concerns\HasEmsUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EventTemplate extends Model
{
    use HasEmsUuid, HasFactory;

    protected $table = 'ems_event_templates';

    protected $fillable = [
        'uuid',
        'name',
        'description',
        'category_id',
        'capacity',
        'is_public',
        'waitlist_enabled',
        'max_tickets_per_order',
        'max_registrations_per_attendee',
        'registration_deadline_offset_days',
        'settings',
        'is_default',
        'archived_at',
    ];

    protected $casts = [
        'capacity' => 'integer',
        'is_public' => 'boolean',
        'waitlist_enabled' => 'boolean',
        'max_tickets_per_order' => 'integer',
        'max_registrations_per_attendee' => 'integer',
        'registration_deadline_offset_days' => 'integer',
        'settings' => 'array',
        'is_default' => 'boolean',
        'archived_at' => 'datetime',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(EventCategory::class, 'category_id');
    }
}
