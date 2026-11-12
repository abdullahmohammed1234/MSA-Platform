<?php

namespace App\Pm\Models;

use App\Models\Department;
use App\Models\User;
use App\Pm\Enums\DepartmentRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PmDepartmentMembership extends Model
{
    use HasFactory;

    protected $table = 'pm_department_memberships';

    protected $fillable = [
        'department_id',
        'user_id',
        'department_role',
        'status',
        'start_date',
        'end_date',
        'assigned_by',
    ];

    protected $casts = [
        'department_role' => DepartmentRole::class,
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function isActive(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        $now = now()->toDateString();
        $startDate = $this->start_date ? \Illuminate\Support\Carbon::parse($this->start_date)->toDateString() : null;
        if ($startDate && $startDate > $now) {
            return false;
        }

        $endDate = $this->end_date ? \Illuminate\Support\Carbon::parse($this->end_date)->toDateString() : null;
        if ($endDate && $endDate < $now) {
            return false;
        }

        return true;
    }

    public function scopeActive($query)
    {
        $today = now()->toDateString();
        return $query->where('status', 'active')
            ->whereDate('start_date', '<=', $today)
            ->where(function ($q) use ($today) {
                $q->whereNull('end_date')->orWhereDate('end_date', '>=', $today);
            });
    }

    public function isLead(): bool
    {
        return $this->department_role === DepartmentRole::Lead;
    }

    public function isCoordinator(): bool
    {
        return $this->department_role === DepartmentRole::Coordinator;
    }

    /**
     * Compatibility accessor for role attribute.
     */
    public function getRoleAttribute(): ?DepartmentRole
    {
        return $this->department_role;
    }
}
