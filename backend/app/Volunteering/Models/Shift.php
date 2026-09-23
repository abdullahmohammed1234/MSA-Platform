<?php

namespace App\Volunteering\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shift extends Model
{
    use HasFactory;

    protected $table = 'volunteering_shifts';

    protected $fillable = [
        'opportunity_id',
        'team_id',
        'name',
        'start_at',
        'end_at',
        'capacity',
        'status',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'capacity' => 'integer',
    ];

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class, 'opportunity_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team_id');
    }

    public function signups(): HasMany
    {
        return $this->hasMany(Signup::class, 'shift_id');
    }

    public function activeSignups(): HasMany
    {
        return $this->hasMany(Signup::class, 'shift_id')
            ->whereIn('status', ['signed_up', 'confirmed', 'completed']);
    }
}
