<?php

namespace App\Ems\Http\Resources;

use App\Ems\Support\EmsPermissions;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The authenticated user as the EMS sees them.
 *
 * Deliberately distinct from the platform's App\Http\Resources\Auth\UserResource:
 * this narrows `permissions` to the EMS namespace so the EMS frontend is not
 * handed the user's academy or CMS capabilities, and reports the module-level
 * access flag the shell needs.
 *
 * @mixin User
 */
class EmsCurrentUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $emsPermissions = $this->emsPermissions();

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'email' => $this->email,
            'avatar' => $this->avatar,
            'is_active' => (bool) $this->is_active,

            'roles' => $this->roles->map(fn ($role): array => [
                'slug' => $role->slug,
                'name' => $role->name,
            ])->values()->all(),

            'permissions' => $emsPermissions,

            // True when the user holds any EMS capability at all. The frontend
            // uses this to decide between the EMS shell and the "no access"
            // screen, but every individual action is still enforced serverside.
            'has_ems_access' => $emsPermissions !== [],

            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * The user's effective EMS permissions, resolved through direct grants and
     * role inheritance. Platform super-admin/admin hold everything by way of
     * the trait's bypass, which is reflected here rather than special-cased in
     * the client.
     *
     * @return array<int, string>
     */
    private function emsPermissions(): array
    {
        return array_values(array_filter(
            EmsPermissions::all(),
            fn (string $permission): bool => $this->resource->hasPermission($permission)
        ));
    }
}
