<?php

namespace App\Volunteering\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Team extends Model
{
    use HasFactory;

    protected $table = 'volunteering_teams';

    protected $fillable = [
        'opportunity_id',
        'name',
        'description',
        'capacity',
        'status',
        'ordering',
    ];

    protected $casts = [
        'capacity' => 'integer',
        'ordering' => 'integer',
    ];

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class, 'opportunity_id');
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class, 'team_id');
    }

    public function signups(): HasMany
    {
        return $this->hasMany(Signup::class, 'team_id');
    }
}
