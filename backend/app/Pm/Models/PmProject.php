<?php

namespace App\Pm\Models;

use App\Models\Department;
use App\Models\User;
use App\Pm\Enums\ProjectPriority;
use App\Pm\Enums\ProjectStatus;
use App\Pm\Enums\ProjectVisibility;
use App\Pm\Services\DepartmentMembershipService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class PmProject extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'pm_projects';

    protected $fillable = [
        'uuid',
        'name',
        'slug',
        'description',
        'department_id',
        'visibility',
        'status',
        'priority',
        'start_date',
        'due_date',
        'owner_id',
        'created_by',
        'updated_by',
        'completed_at',
    ];

    protected $casts = [
        'visibility' => ProjectVisibility::class,
        'status' => ProjectStatus::class,
        'priority' => ProjectPriority::class,
        'start_date' => 'date',
        'due_date' => 'date',
        'completed_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($project) {
            if (empty($project->uuid)) {
                $project->uuid = (string) Str::uuid();
            }
            if (empty($project->slug)) {
                $project->slug = Str::slug($project->name) . '-' . substr((string) Str::uuid(), 0, 8);
            }
            if (empty($project->created_by)) {
                $project->created_by = auth()->id() ?? $project->owner_id;
            }
        });
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function participatingDepartments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class, 'pm_project_departments', 'project_id', 'department_id')
            ->withPivot(['id', 'created_by'])
            ->withTimestamps();
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function projectMembers(): HasMany
    {
        return $this->hasMany(PmProjectMember::class, 'project_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'pm_project_members', 'project_id', 'user_id')
            ->withPivot(['id', 'project_role', 'invitation_status', 'invited_by', 'invited_at', 'accepted_at', 'revoked_at'])
            ->withTimestamps();
    }

    public function acceptedMembers(): BelongsToMany
    {
        return $this->members()->wherePivot('invitation_status', 'accepted');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(PmTask::class, 'project_id');
    }

    public function taskLists(): HasMany
    {
        return $this->hasMany(PmTaskList::class, 'project_id')->orderBy('order_index', 'asc');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(PmAttachment::class, 'project_id');
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(PmMilestone::class, 'project_id');
    }

    public function calendarFeeds(): HasMany
    {
        return $this->hasMany(PmCalendarFeed::class, 'project_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(PmComment::class, 'project_id');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(PmActivityLog::class, 'project_id');
    }

    public function isDepartmentPrivate(): bool
    {
        return $this->visibility === ProjectVisibility::DepartmentPrivate;
    }

    public function isSharedMsa(): bool
    {
        return $this->visibility === ProjectVisibility::SharedMsa;
    }

    /**
     * Scope query to projects accessible to the user (Option B).
     * Accessible if:
     * 1. shared_msa visibility
     * 2. department_private AND (user in owning dept OR user in any participating dept OR accepted project member)
     */
    public function scopeAccessibleTo(Builder $query, User $user): Builder
    {
        $deptService = app(DepartmentMembershipService::class);
        $activeDeptIds = $deptService->getUserDepartmentIds($user);
        $acceptedProjectIds = PmProjectMember::query()
            ->where('user_id', $user->id)
            ->accepted()
            ->pluck('project_id')
            ->toArray();

        return $query->where(function ($q) use ($activeDeptIds, $acceptedProjectIds) {
            $q->where('visibility', ProjectVisibility::SharedMsa->value)
                ->orWhere(function ($sub) use ($activeDeptIds, $acceptedProjectIds) {
                    $sub->where('visibility', ProjectVisibility::DepartmentPrivate->value)
                        ->where(function ($inner) use ($activeDeptIds, $acceptedProjectIds) {
                            if (!empty($activeDeptIds)) {
                                $inner->whereIn('department_id', $activeDeptIds)
                                    ->orWhereHas('participatingDepartments', function ($pDeptQ) use ($activeDeptIds) {
                                        $pDeptQ->whereIn('departments.id', $activeDeptIds);
                                    });
                            }
                            if (!empty($acceptedProjectIds)) {
                                $inner->orWhereIn('id', $acceptedProjectIds);
                            }
                            if (empty($activeDeptIds) && empty($acceptedProjectIds)) {
                                $inner->whereRaw('0 = 1');
                            }
                        });
                });
        });
    }
}
