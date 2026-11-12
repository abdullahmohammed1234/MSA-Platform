<?php

namespace App\Pm\Models;

use App\Models\User;
use App\Pm\Enums\DependencyType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PmTaskDependency extends Model
{
    use HasFactory;

    protected $table = 'pm_task_dependencies';

    protected $attributes = [
        'dependency_type' => 'finish_to_start',
        'lag_days' => 0,
    ];

    protected $fillable = [
        'project_id',
        'task_id',
        'depends_on_task_id',
        'dependency_type',
        'lag_days',
        'created_by',
    ];

    protected $casts = [
        'dependency_type' => DependencyType::class,
        'lag_days' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(PmProject::class, 'project_id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(PmTask::class, 'task_id');
    }

    public function dependsOnTask(): BelongsTo
    {
        return $this->belongsTo(PmTask::class, 'depends_on_task_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isFinishToStart(): bool
    {
        return ($this->dependency_type ?? DependencyType::FinishToStart) === DependencyType::FinishToStart;
    }

    public function isStartToStart(): bool
    {
        return $this->dependency_type === DependencyType::StartToStart;
    }

    public function isFinishToFinish(): bool
    {
        return $this->dependency_type === DependencyType::FinishToFinish;
    }
}
