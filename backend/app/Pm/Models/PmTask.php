<?php

namespace App\Pm\Models;

use App\Models\User;
use App\Pm\Enums\ProjectPriority;
use App\Pm\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class PmTask extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'pm_tasks';

    protected $fillable = [
        'uuid',
        'project_id',
        'milestone_id',
        'task_list_id',
        'parent_task_id',
        'title',
        'name',
        'description',
        'status',
        'priority',
        'start_date',
        'due_date',
        'estimated_hours',
        'effort_source',
        'assigned_to',
        'created_by',
        'completed_at',
    ];

    protected $casts = [
        'status' => TaskStatus::class,
        'priority' => ProjectPriority::class,
        'start_date' => 'date',
        'due_date' => 'date',
        'estimated_hours' => 'decimal:2',
        'effort_source' => 'string',
        'completed_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($task) {
            if (empty($task->uuid)) {
                $task->uuid = (string) Str::uuid();
            }
            if (empty($task->created_by)) {
                $task->created_by = auth()->id() ?? ($task->project ? $task->project->owner_id : 1);
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

    /**
     * Compute effective estimated hours (manual estimate or dynamic inference).
     */
    public function getEffectiveEstimatedHoursAttribute(): float
    {
        if ($this->estimated_hours !== null && (float) $this->estimated_hours > 0) {
            return (float) $this->estimated_hours;
        }

        return $this->calculateInferredEffort();
    }

    /**
     * Calculate inferred effort based on working days in schedule.
     */
    public function calculateInferredEffort(): float
    {
        $rate = (float) config('pm.effort.default_inferred_hours_per_day', 2.0);

        if (!$this->start_date && !$this->due_date) {
            return 0.0;
        }

        $start = $this->start_date ? \Carbon\Carbon::parse($this->start_date) : \Carbon\Carbon::parse($this->due_date);
        $due = $this->due_date ? \Carbon\Carbon::parse($this->due_date) : \Carbon\Carbon::parse($this->start_date);

        if ($due->lt($start)) {
            $due = $start->copy();
        }

        // Count working days (Mon-Fri)
        $workingDays = 0;
        $curr = $start->copy();
        while ($curr->lte($due)) {
            if ($curr->isWeekday()) {
                $workingDays++;
            }
            $curr->addDay();
        }

        // Zero-working-day fallback: 1 working day equivalent to capture weekend staging
        if ($workingDays === 0) {
            $workingDays = 1;
        }

        return round($workingDays * $rate, 2);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(PmProject::class, 'project_id');
    }

    public function milestone(): BelongsTo
    {
        return $this->belongsTo(PmMilestone::class, 'milestone_id');
    }

    public function parentTask(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_task_id');
    }

    public function subtasks(): HasMany
    {
        return $this->hasMany(self::class, 'parent_task_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'pm_task_assignees', 'task_id', 'user_id')
            ->withPivot(['id', 'assigned_by', 'assigned_at'])
            ->withTimestamps();
    }

    public function taskList(): BelongsTo
    {
        return $this->belongsTo(PmTaskList::class, 'task_list_id');
    }

    public function dependencies(): BelongsToMany
    {
        return $this->belongsToMany(PmTask::class, 'pm_task_dependencies', 'task_id', 'depends_on_task_id')
            ->withPivot(['id', 'dependency_type', 'lag_days', 'created_by', 'created_at']);
    }

    public function dependents(): BelongsToMany
    {
        return $this->belongsToMany(PmTask::class, 'pm_task_dependencies', 'depends_on_task_id', 'task_id')
            ->withPivot(['id', 'dependency_type', 'lag_days', 'created_by', 'created_at']);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(PmAttachment::class, 'task_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(PmComment::class, 'task_id');
    }

    public function isCompleted(): bool
    {
        return $this->status === TaskStatus::Completed;
    }
}
