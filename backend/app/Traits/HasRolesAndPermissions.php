<?php

namespace App\Traits;

use App\Models\Permission;
use App\Models\Role;

trait HasRolesAndPermissions
{
    public function roles()
    {
        return $this->belongsToMany(Role::class);
    }

    public function permissions()
    {
        return $this->belongsToMany(Permission::class);
    }

    public function hasRole(string $role): bool
    {
        return $this->roles()->where('slug', $role)->orWhere('name', $role)->exists();
    }

    public function hasAnyRole(array $roles): bool
    {
        return $this->roles()->whereIn('slug', $roles)->orWhereIn('name', $roles)->exists();
    }

    public function hasPermission(string $permission): bool
    {
        // 1. Super Admin and Admin have all permissions bypass
        if ($this->hasRole('super-admin') || $this->hasRole('admin')) {
            return true;
        }

        // 2. Check direct permissions
        if ($this->permissions()->where('slug', $permission)->exists()) {
            return true;
        }

        // 3. Check inherited permissions from roles
        return $this->roles()->whereHas('permissions', function ($query) use ($permission) {
            $query->where('slug', $permission);
        })->exists();
    }

    public function assignRole(string|Role $role): void
    {
        if (is_string($role)) {
            $role = Role::where('slug', $role)->orWhere('name', $role)->firstOrFail();
        }
        $this->roles()->syncWithoutDetaching([$role->id]);
    }

    public function removeRole(string|Role $role): void
    {
        if (is_string($role)) {
            $role = Role::where('slug', $role)->orWhere('name', $role)->first();
        }
        if ($role) {
            $this->roles()->detach($role->id);
        }
    }

    public function syncRoles(array $roles): void
    {
        $roleIds = [];
        foreach ($roles as $role) {
            if (is_string($role)) {
                $roleModel = Role::where('slug', $role)->orWhere('name', $role)->first();
                if ($roleModel) {
                    $roleIds[] = $roleModel->id;
                }
            } elseif ($role instanceof Role) {
                $roleIds[] = $role->id;
            }
        }
        $this->roles()->sync($roleIds);
    }

    public function givePermission(string|Permission $permission): void
    {
        if (is_string($permission)) {
            $permission = Permission::where('slug', $permission)->orWhere('name', $permission)->firstOrFail();
        }
        $this->permissions()->syncWithoutDetaching([$permission->id]);
    }

    public function revokePermission(string|Permission $permission): void
    {
        if (is_string($permission)) {
            $permission = Permission::where('slug', $permission)->orWhere('name', $permission)->first();
        }
        if ($permission) {
            $this->permissions()->detach($permission->id);
        }
    }

    public function syncPermissions(array $permissions): void
    {
        $permissionIds = [];
        foreach ($permissions as $permission) {
            if (is_string($permission)) {
                $permissionModel = Permission::where('slug', $permission)->orWhere('name', $permission)->first();
                if ($permissionModel) {
                    $permissionIds[] = $permissionModel->id;
                }
            } elseif ($permission instanceof Permission) {
                $permissionIds[] = $permission->id;
            }
        }
        $this->permissions()->sync($permissionIds);
    }

    // Shortcut checks
    public function canManageUsers(): bool
    {
        return $this->hasPermission('manage_users');
    }

    public function canManageRoles(): bool
    {
        return $this->hasPermission('manage_roles');
    }

    public function canManagePermissions(): bool
    {
        return $this->hasPermission('manage_permissions');
    }

    public function canManageCourses(): bool
    {
        return $this->hasPermission('manage_courses');
    }

    public function canManageLessons(): bool
    {
        return $this->hasPermission('manage_lessons');
    }

    public function canManageQuizzes(): bool
    {
        return $this->hasPermission('manage_quizzes');
    }

    public function canManageCertificates(): bool
    {
        return $this->hasPermission('manage_certificates');
    }

    public function canManageEvents(): bool
    {
        return $this->hasPermission('manage_events');
    }

    public function canManageAnnouncements(): bool
    {
        return $this->hasPermission('manage_announcements');
    }

    public function canManageResources(): bool
    {
        return $this->hasPermission('manage_resources');
    }

    public function canManageVolunteers(): bool
    {
        return $this->hasPermission('manage_volunteers');
    }

    public function canManageMentors(): bool
    {
        return $this->hasPermission('manage_mentors');
    }

    public function canViewAnalytics(): bool
    {
        return $this->hasPermission('view_analytics');
    }

    public function canViewReports(): bool
    {
        return $this->hasPermission('view_reports');
    }

    public function canManageAnalytics(): bool
    {
        return $this->hasPermission('manage_analytics');
    }

    public function canExportAnalytics(): bool
    {
        return $this->hasPermission('export_analytics');
    }

    public function canManageSettings(): bool
    {
        return $this->hasPermission('manage_settings');
    }
}
