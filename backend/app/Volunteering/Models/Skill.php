<?php

namespace App\Volunteering\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Skill extends Model
{
    use HasFactory;

    protected $table = 'volunteering_skills';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'category',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($skill) {
            if (empty($skill->slug)) {
                $skill->slug = Str::slug($skill->name);
            }
        });
    }

    public function profiles(): BelongsToMany
    {
        return $this->belongsToMany(VolunteerProfile::class, 'volunteering_profile_skills', 'skill_id', 'profile_id')
            ->withPivot('proficiency_level')
            ->withTimestamps();
    }

    public function opportunities(): BelongsToMany
    {
        return $this->belongsToMany(Opportunity::class, 'volunteering_opportunity_skills', 'skill_id', 'opportunity_id')
            ->withPivot(['is_required', 'min_proficiency'])
            ->withTimestamps();
    }
}
