<?php

namespace App\Ems\Http\Controllers\V1;

use App\Ems\Http\Controllers\EmsController;
use App\Ems\Http\Resources\EmsCurrentUserResource;
use App\Ems\Support\ApiResponse;
use App\Ems\Support\EmsPermissions;
use App\Ems\Support\EmsRoles;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Identity and access endpoints for the EMS.
 *
 * There is no login or logout here on purpose. The EMS consumes the platform's
 * existing Sanctum endpoints (POST /api/v1/auth/login, POST /api/v1/auth/logout)
 * rather than standing up a second account system. This controller only
 * answers "who am I inside the EMS" and "what does the EMS access model look
 * like".
 */
class AccessController extends EmsController
{
    /**
     * GET /api/v1/ems/users/me
     *
     * The authenticated user with their EMS permissions resolved. The frontend
     * builds its navigation from this; the server still enforces every action.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load(['roles.permissions', 'permissions']);

        return ApiResponse::success(
            new EmsCurrentUserResource($user),
            'Authenticated user retrieved successfully.'
        );
    }

    /**
     * GET /api/v1/ems/roles
     *
     * The EMS roles and the permissions attached to each.
     */
    public function roles(): JsonResponse
    {
        $this->authorizeSystemRead();

        $emsSlugs = array_merge(
            [EmsRoles::SUPER_ADMIN],
            array_column(EmsRoles::definitions(), 'slug')
        );

        $roles = Role::query()
            ->with('permissions')
            ->whereIn('slug', $emsSlugs)
            ->orderBy('id')
            ->get()
            ->map(fn (Role $role): array => [
                'uuid' => $role->uuid,
                'name' => $role->name,
                'slug' => $role->slug,
                'description' => $role->description,
                'permissions' => $role->permissions
                    ->where('module', EmsPermissions::MODULE)
                    ->pluck('slug')
                    ->values()
                    ->all(),
            ])
            ->values();

        return ApiResponse::success($roles, 'EMS roles retrieved successfully.', [
            'total' => $roles->count(),
        ]);
    }

    /**
     * GET /api/v1/ems/permissions
     *
     * The EMS permission catalogue, grouped for display.
     */
    public function permissions(): JsonResponse
    {
        $this->authorizeSystemRead();

        $descriptions = collect(EmsPermissions::definitions())->keyBy('slug');

        $permissions = Permission::query()
            ->where('module', EmsPermissions::MODULE)
            ->orderBy('id')
            ->get()
            ->map(fn (Permission $permission): array => [
                'uuid' => $permission->uuid,
                'name' => $permission->name,
                'slug' => $permission->slug,
                'description' => $permission->description,
                'group' => $descriptions[$permission->slug]['group'] ?? 'Other',
            ])
            ->values();

        return ApiResponse::success($permissions, 'EMS permissions retrieved successfully.', [
            'total' => $permissions->count(),
            'groups' => $permissions->pluck('group')->unique()->values()->all(),
        ]);
    }

    /**
     * Reading the access model requires an explicit system permission rather
     * than merely being signed in.
     */
    private function authorizeSystemRead(): void
    {
        abort_unless(
            request()->user()?->hasPermission(EmsPermissions::SYSTEM_VIEW),
            403,
            'This action is unauthorized.'
        );
    }
}
