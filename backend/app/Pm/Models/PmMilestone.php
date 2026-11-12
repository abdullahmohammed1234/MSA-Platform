<?php

namespace App\Pm\Models;

use App\Models\User;
use App\Pm\Enums\MilestoneStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class PmMilestone extends Model
{
    use HasFactory;

    protected $table = 'pm_milestones';

    protected $fillable = [
        'uuid',
        'project_id',
        'title',
        'name',
        'description',
        'due_date',
        'status',
        'created_by',
        'completed_at',
    ];

    protected $casts = [
        'status' => MilestoneStatus::class,
        'due_date' => 'date',
        'completed_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($milestone) {
            if (empty($milestone->uuid)) {
                $milestone->uuid = (string) Str::uuid();
            }
            if (empty($milestone->created_by)) {
                $milestone->created_by = auth()->id() ?? ($milestone->project ? $milestone->project->owner_id : 1);
            }
        });
    }

    public function getNameAttribute(): ?string
    {
        return $this->title;
    }

    public function setNameAttribute($value): void
    {
        $this->attributes['title'] = $value;
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(PmProject::class, 'project_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(PmTask::class, 'milestone_id');
    }

    public function isReached(): bool
    {
        return $this->status === MilestoneStatus::Reached;
    }

    public function isOverdue(): bool
    {
        if ($this->isReached() || !$this->due_date) {
            return false;
        }

        return $this->due_date->isPast();
    }
}
