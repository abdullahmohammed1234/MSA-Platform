<?php

namespace App\Volunteering\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Interest extends Model
{
    use HasFactory;

    protected $table = 'volunteering_interests';

    protected $fillable = [
        'name',
        'slug',
        'description',
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

        static::creating(function ($interest) {
            if (empty($interest->slug)) {
                $interest->slug = Str::slug($interest->name);
            }
        });
    }

    public function profiles(): BelongsToMany
    {
        return $this->belongsToMany(VolunteerProfile::class, 'volunteering_profile_interests', 'interest_id', 'profile_id')
            ->withTimestamps();
    }

    public function opportunities(): BelongsToMany
    {
        return $this->belongsToMany(Opportunity::class, 'volunteering_opportunity_interests', 'interest_id', 'opportunity_id')
            ->withTimestamps();
    }
}
