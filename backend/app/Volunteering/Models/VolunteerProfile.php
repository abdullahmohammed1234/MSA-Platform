<?php

namespace App\Volunteering\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VolunteerProfile extends Model
{
    use HasFactory;

    protected $table = 'volunteering_profiles';

    protected $fillable = [
        'user_id',
        'bio',
        'experience_level',
        'years_experience',
        'preferred_hours_per_week',
        'availability_days',
        'availability_times',
        'preferred_categories',
        'profile_completion_percentage',
        'privacy_level',
    ];

    protected $casts = [
        'years_experience' => 'float',
        'preferred_hours_per_week' => 'integer',
        'profile_completion_percentage' => 'integer',
        'availability_days' => 'array',
        'availability_times' => 'array',
        'preferred_categories' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class, 'volunteering_profile_skills', 'profile_id', 'skill_id')
            ->withPivot('proficiency_level')
            ->withTimestamps();
    }

    public function interests(): BelongsToMany
    {
        return $this->belongsToMany(Interest::class, 'volunteering_profile_interests', 'profile_id', 'interest_id')
            ->withTimestamps();
    }

    public function experiences(): HasMany
    {
        return $this->hasMany(VolunteerExperience::class, 'profile_id')->orderBy('start_date', 'desc');
    }

    /**
     * Calculate and update the profile completion percentage.
     */
    public function calculateCompletionPercentage(): int
    {
        $points = 0;
        $total = 5;

        // 1. Basic details (bio or experience level set)
        if (!empty($this->bio) || !empty($this->experience_level)) {
            $points++;
        }

        // 2. Skills attached
        if ($this->skills()->count() > 0) {
            $points++;
        }

        // 3. Interests attached
        if ($this->interests()->count() > 0) {
            $points++;
        }

        // 4. Availability configured
        if (!empty($this->availability_days) || !empty($this->availability_times)) {
            $points++;
        }

        // 5. External experiences or preferred categories
        if ($this->experiences()->count() > 0 || !empty($this->preferred_categories)) {
            $points++;
        }

        $percentage = (int) round(($points / $total) * 100);
        $this->update(['profile_completion_percentage' => $percentage]);

        return $percentage;
    }
}
