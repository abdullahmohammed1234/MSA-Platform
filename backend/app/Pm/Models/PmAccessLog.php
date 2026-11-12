<?php

namespace App\Pm\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PmAccessLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'pm_access_logs';

    protected $fillable = [
        'target_user_id',
        'actor_user_id',
        'action',
        'previous_value',
        'previous_state',
        'new_value',
        'new_state',
        'reason',
        'notes',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'previous_value' => 'array',
        'new_value' => 'array',
        'created_at' => 'datetime',
    ];

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function actorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function actor(): BelongsTo
    {
        return $this->actorUser();
    }

    public function getPreviousStateAttribute()
    {
        return $this->previous_value;
    }

    public function setPreviousStateAttribute($value): void
    {
        $this->attributes['previous_value'] = is_array($value) ? json_encode($value) : $value;
    }

    public function getNewStateAttribute()
    {
        return $this->new_value;
    }

    public function setNewStateAttribute($value): void
    {
        $this->attributes['new_value'] = is_array($value) ? json_encode($value) : $value;
    }

    public function getNotesAttribute(): ?string
    {
        return $this->reason;
    }

    public function setNotesAttribute($value): void
    {
        $this->attributes['reason'] = $value;
    }
}
