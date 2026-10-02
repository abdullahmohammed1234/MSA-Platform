<?php

namespace App\Volunteering\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VolunteerExperience extends Model
{
    use HasFactory;

    protected $table = 'volunteering_experiences';

    protected $fillable = [
        'profile_id',
        'organization',
        'role_title',
        'description',
        'start_date',
        'end_date',
        'is_current',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_current' => 'boolean',
    ];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(VolunteerProfile::class, 'profile_id');
    }
}
