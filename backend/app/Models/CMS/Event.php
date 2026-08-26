<?php

namespace App\Models\CMS;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @deprecated Phase 9 — Legacy CMS event table retained for archival only.
 * Application code must not use this model. EMS owns events (`App\Ems\Models\Event`).
 * Tables `events` / `event_registrations` are intentionally not dropped.
 */
class Event extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'title',
        'description',
        'location',
        'date',
        'time',
        'start_date',
        'end_date',
        'registration_url',
        'image',
        'category',
        'status',
        'spots_left',
        'featured',
        'registration_deadline',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'featured' => 'boolean',
        'registration_deadline' => 'date',
        'spots_left' => 'integer',
    ];

    public function revisions()
    {
        return $this->morphMany(CmsRevision::class, 'revisable');
    }

    public function registrations()
    {
        return $this->hasMany(EventRegistration::class);
    }
}
