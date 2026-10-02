<?php

namespace App\Volunteering\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Achievement extends Model
{
    use HasFactory;

    protected $table = 'volunteering_achievements';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'category',
        'icon',
        'rule_type',
        'criteria_config',
        'points',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'criteria_config' => 'array',
        'is_active' => 'boolean',
        'points' => 'integer',
        'sort_order' => 'integer',
    ];

    public function userAchievements(): HasMany
    {
        return $table = $this->hasMany(UserAchievement::class, 'achievement_id');
    }
}
