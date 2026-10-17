<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Action-level authorization for the volunteering administration API.
 *
 * `app.access:volunteering` decides who may enter the console; this decides
 * what they may do there. An explicit application grant or an operator role is
 * full access. Everyone else needs the granular VMS permission for the action,
 * or `vms.manage`.
 */
class VolunteeringPermission
{
    private const OPERATOR_ROLES = ['super-admin', 'admin', 'vms-administrator', 'vms-manager', 'volunteer-administrator'];

    private const COORDINATOR_PERMISSIONS = ['vms.view', 'vms.manage_signups'];

    public function handle(Request $request, Closure $next, string $permissions): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (self::permits($user, explode('|', $permissions))) {
            return $next($request);
        }

        return response()->json(['message' => 'You do not have permission to perform this volunteering action.'], 403);
    }

    public static function permits(User $user, array $permissions): bool
    {
        if ($user->hasAnyRole(self::OPERATOR_ROLES)) {
            return true;
        }

        if ($user->applicationAccess()->whereIn('application', ['volunteering', 'vms'])->exists()) {
            return true;
        }

        if ($user->hasPermission('vms.manage')) {
            return true;
        }

        if ($user->hasRole('volunteer-coordinator') && array_intersect($permissions, self::COORDINATOR_PERMISSIONS) !== []) {
            return true;
        }

        foreach ($permissions as $permission) {
            if ($user->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }
}
