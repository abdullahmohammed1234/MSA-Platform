<?php

namespace App\Models;

use App\Pm\Models\PmDepartmentMembership;
use App\Pm\Models\PmProject;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Department extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'departments';

    protected $fillable = [
        'uuid',
        'name',
        'slug',
        'description',
        'color',
        'is_active',
        'display_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'display_order' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($department) {
            if (empty($department->uuid)) {
                $department->uuid = (string) Str::uuid();
            }
            if (empty($department->slug)) {
                $department->slug = Str::slug($department->name);
            }
        });
    }

    public function projects(): HasMany
    {
        return $this->hasMany(PmProject::class, 'department_id');
    }

    public function participatingProjects(): BelongsToMany
    {
        return $this->belongsToMany(PmProject::class, 'pm_project_departments', 'department_id', 'project_id')
            ->withPivot(['id', 'created_by'])
            ->withTimestamps();
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(PmDepartmentMembership::class, 'department_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'pm_department_memberships', 'department_id', 'user_id')
            ->withPivot(['id', 'department_role', 'status', 'start_date', 'end_date', 'assigned_by'])
            ->withTimestamps();
    }

    public function activeMembers(): BelongsToMany
    {
        return $this->members()->wherePivot('status', 'active');
    }

    public function leads(): BelongsToMany
    {
        return $this->activeMembers()->wherePivot('department_role', 'lead');
    }

    public function coordinators(): BelongsToMany
    {
        return $this->activeMembers()->wherePivot('department_role', 'coordinator');
    }
}
