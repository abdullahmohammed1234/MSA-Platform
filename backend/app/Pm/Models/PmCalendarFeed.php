<?php

namespace App\Pm\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PmCalendarFeed extends Model
{
    use HasFactory;

    protected $table = 'pm_calendar_feeds';

    protected $fillable = [
        'uuid',
        'user_id',
        'project_id',
        'token_hash',
        'name',
        'include_tasks',
        'include_milestones',
        'include_project_deadlines',
        'only_assigned_to_me',
        'last_accessed_at',
        'last_accessed_ip',
        'revoked_at',
    ];

    /**
     * Token hash and internal primary ID should be hidden from serialization for security.
     */
    protected $hidden = [
        'token_hash',
    ];

    protected $casts = [
        'include_tasks' => 'boolean',
        'include_milestones' => 'boolean',
        'include_project_deadlines' => 'boolean',
        'only_assigned_to_me' => 'boolean',
        'last_accessed_at' => 'datetime',
        'revoked_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($feed) {
            if (empty($feed->uuid)) {
                $feed->uuid = (string) Str::uuid();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(PmProject::class, 'project_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at');
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function revoke(): void
    {
        $this->update(['revoked_at' => now()]);
    }

    public function recordAccess(?string $ip): void
    {
        $this->update([
            'last_accessed_at' => now(),
            'last_accessed_ip' => $ip,
        ]);
    }
}

