<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ApplicationAccessService;
use App\Services\Security\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ApplicationAccessController extends Controller
{
    protected $appAccessService;
    protected $auditLogger;

    public function __construct(ApplicationAccessService $appAccessService, AuditLogger $auditLogger)
    {
        $this->appAccessService = $appAccessService;
        $this->auditLogger = $auditLogger;
    }

    /**
     * List all users with their application access.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorizeAccess();

        $search = $request->query('search');
        
        $query = User::query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->paginate(15);

        $items = collect($users->items())->map(function ($user) {
            return [
                'id' => $user->id,
                'uuid' => $user->uuid,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->roles->pluck('slug')->toArray(),
                'application_access' => $this->appAccessService->accessibleApplications($user)
            ];
        });

        return response()->json([
            'users' => $items,
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total()
            ]
        ]);
    }

    /**
     * Show application access for a specific user.
     */
    public function show(User $user): JsonResponse
    {
        $this->authorizeAccess();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'uuid' => $user->uuid,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->roles->pluck('slug')->toArray(),
            ],
            'application_access' => $this->appAccessService->accessibleApplications($user)
        ]);
    }

    /**
     * Update application access for a user.
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $this->authorizeAccess();

        $validated = $request->validate([
            'access' => 'required|array',
            'access.*' => 'required|boolean'
        ]);

        $admin = Auth::user();

        foreach ($validated['access'] as $app => $grant) {
            if ($grant) {
                $this->appAccessService->grant($user, $app, $admin);
            } else {
                $this->appAccessService->revoke($user, $app, $admin);
            }
        }

        return response()->json([
            'message' => 'Application access updated successfully.',
            'application_access' => $this->appAccessService->accessibleApplications($user)
        ]);
    }

    /**
     * Authorize that the current user is a privileged admin or holds manage_users permission.
     */
    private function authorizeAccess(): void
    {
        $user = Auth::user();
        if (!$user || (!$user->hasAnyRole(['admin', 'super-admin']) && !$user->hasPermission('manage_users'))) {
            abort(403, 'Unauthorized.');
        }
    }
}
