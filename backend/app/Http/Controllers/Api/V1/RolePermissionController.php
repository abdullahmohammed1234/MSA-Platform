<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\AuthorizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class RolePermissionController extends Controller
{
    protected $authService;

    public function __construct(AuthorizationService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * List all roles with their permissions.
     */
    public function indexRoles(): JsonResponse
    {
        $this->authorize('viewAny', Role::class);

        $roles = Role::with('permissions')->get();

        return response()->json(['roles' => $roles]);
    }

    /**
     * Create a new role.
     */
    public function storeRole(Request $request): JsonResponse
    {
        $this->authorize('create', Role::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name',
            'slug' => 'required|string|max:255|unique:roles,slug',
            'description' => 'nullable|string|max:500',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|exists:permissions,slug',
        ]);

        $role = Role::create([
            'uuid' => (string) Str::uuid(),
            'name' => $validated['name'],
            'slug' => Str::slug($validated['slug']),
            'description' => $validated['description'] ?? null,
        ]);

        if (!empty($validated['permissions'])) {
            $this->authService->syncRolePermissions($role, $validated['permissions'], auth()->id());
        }

        // Log role creation
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'role_created',
            'target_type' => Role::class,
            'target_id' => $role->id,
            'description' => "Created role '{$role->name}'.",
            'payload' => $role->toArray(),
        ]);

        return response()->json([
            'message' => 'Role created successfully.',
            'role' => $role->load('permissions'),
        ], 201);
    }

    /**
     * Update an existing role.
     */
    public function updateRole(Request $request, Role $role): JsonResponse
    {
        $this->authorize('update', $role);

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name,' . $role->id,
            'slug' => 'required|string|max:255|unique:roles,slug,' . $role->id,
            'description' => 'nullable|string|max:500',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|exists:permissions,slug',
        ]);

        $role->update([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['slug']),
            'description' => $validated['description'] ?? null,
        ]);

        if (isset($validated['permissions'])) {
            $this->authService->syncRolePermissions($role, $validated['permissions'], auth()->id());
        }

        return response()->json([
            'message' => 'Role updated successfully.',
            'role' => $role->load('permissions'),
        ]);
    }

    /**
     * Delete a role.
     */
    public function destroyRole(Role $role): JsonResponse
    {
        $this->authorize('delete', $role);

        $roleData = $role->toArray();
        $role->delete();

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'role_deleted',
            'target_type' => Role::class,
            'target_id' => $role->id,
            'description' => "Deleted role '{$roleData['name']}'.",
            'payload' => $roleData,
        ]);

        return response()->json([
            'message' => 'Role deleted successfully.',
        ]);
    }

    /**
     * List all permissions.
     */
    public function indexPermissions(): JsonResponse
    {
        $this->authorize('viewAny', Role::class);

        $permissions = Permission::all();

        return response()->json(['permissions' => $permissions]);
    }

    /**
     * Assign roles to a user.
     */
    public function assignRolesToUser(Request $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $validated = $request->validate([
            'roles' => 'required|array',
            'roles.*' => 'string|exists:roles,slug',
        ]);

        // Enforce Super Admin protection: non-super-admins cannot add/remove super-admin role
        $isAssigningSuperAdmin = in_array('super-admin', $validated['roles']);
        $isCurrentlySuperAdmin = $user->hasRole('super-admin');

        if (($isAssigningSuperAdmin || $isCurrentlySuperAdmin) && !auth()->user()->hasRole('super-admin') && !auth()->user()->hasRole('admin')) {
            return response()->json(['message' => 'Only Super Admins or Admins can manage Super Admin roles.'], 403);
        }

        $this->authService->syncUserRoles($user, $validated['roles'], auth()->id());

        return response()->json([
            'message' => 'User roles updated successfully.',
            'user' => $user->load('roles'),
        ]);
    }

    /**
     * Assign direct permissions to a user.
     */
    public function assignPermissionsToUser(Request $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $validated = $request->validate([
            'permissions' => 'required|array',
            'permissions.*' => 'string|exists:permissions,slug',
        ]);

        if ($user->hasRole('super-admin') && !auth()->user()->hasRole('super-admin') && !auth()->user()->hasRole('admin')) {
            return response()->json(['message' => 'Only Super Admins or Admins can modify permissions for Super Admins.'], 403);
        }

        DB::transaction(function () use ($user, $validated) {
            $oldPermissions = $user->permissions()->pluck('slug')->toArray();
            $user->syncPermissions($validated['permissions']);
            $newPermissions = $user->permissions()->pluck('slug')->toArray();

            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'user_permissions_synced',
                'target_type' => User::class,
                'target_id' => $user->id,
                'description' => "Synced direct permissions for user '{$user->email}'.",
                'payload' => [
                    'before' => $oldPermissions,
                    'after' => $newPermissions,
                ],
            ]);
        });

        return response()->json([
            'message' => 'User permissions updated successfully.',
            'user' => $user->load('permissions'),
        ]);
    }

    /**
     * Retrieve audit logs.
     */
    public function auditLogs(): JsonResponse
    {
        $this->authorize('viewAny', Role::class);

        $logs = AuditLog::with('user')->latest()->paginate(50);

        return response()->json(['audit_logs' => $logs]);
    }
}
