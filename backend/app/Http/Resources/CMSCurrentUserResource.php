<?php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CMSCurrentUserResource extends JsonResource
{
    private const CMS_PERMISSIONS = [
        'manage_homepage',
        'manage_announcements',
        'manage_team',
        'manage_resources',
        'manage_media',
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

        // If user is super-admin or admin, they inherit all CMS permissions
        $isPrivileged = $this->roles->contains(fn($r) => in_array($r->slug, ['admin', 'super-admin']));

        $cmsPermissions = $isPrivileged 
            ? self::CMS_PERMISSIONS 
            : array_values(array_intersect($allPermissions, self::CMS_PERMISSIONS));

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
            'permissions' => $cmsPermissions,
            'has_cms_access' => app(\App\Services\ApplicationAccessService::class)->canAccess($this->resource, 'cms'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
