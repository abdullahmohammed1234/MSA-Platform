<?php

namespace App\Ems\Models;

use App\Ems\Models\Concerns\HasEmsUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventFeedback extends Model
{
    use HasEmsUuid, HasFactory;

    protected $table = 'ems_event_feedbacks';

    protected $fillable = [
        'uuid',
        'event_id',
        'registration_id',
        'user_id',
        'is_anonymous',
        'overall_rating',
        'organization_rating',
        'program_rating',
        'venue_rating',
        'text_feedback',
    ];

    protected $casts = [
        'is_anonymous' => 'boolean',
        'overall_rating' => 'integer',
        'organization_rating' => 'integer',
        'program_rating' => 'integer',
        'venue_rating' => 'integer',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class, 'registration_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
