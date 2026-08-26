<?php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DAMSCurrentUserResource extends JsonResource
{
    private const DAMS_PERMISSIONS = [
        'manage_courses',
        'manage_modules',
        'manage_lessons',
        'manage_quizzes',
        'manage_learning_paths',
        'manage_mentors',
        'manage_students',
        'view_progress',
        'manage_achievements',
        'manage_badges',
        'manage_settings',
        'manage_notifications',
        'manage_discussions',
        'view_analytics',
        'view_reports',
        'manage_analytics',
        'export_analytics',
    ];

    public function toArray(Request $request): array
    {
        $allPermissions = $this->permissions->pluck('slug')->merge(
            $this->roles->flatMap(fn($role) => $role->permissions->pluck('slug'))
        )->unique()->values()->toArray();

        // If user is super-admin or admin, they inherit all DAMS permissions
        $isPrivileged = $this->roles->contains(fn($r) => in_array($r->slug, ['admin', 'super-admin']));

        $damsPermissions = $isPrivileged 
            ? self::DAMS_PERMISSIONS 
            : array_values(array_intersect($allPermissions, self::DAMS_PERMISSIONS));

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'email' => $this->email,
            'avatar' => $this->avatar,
            'is_active' => (bool) $this->is_active,
            'roles' => $this->roles->map(fn($role) => [
                'slug' => $role->slug,
                'name' => $role->name,
            ])->values()->all(),
            'permissions' => $damsPermissions,
            'has_dams_access' => !empty($damsPermissions) || $isPrivileged,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
