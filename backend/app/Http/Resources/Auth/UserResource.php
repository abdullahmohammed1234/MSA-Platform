<?php

namespace App\Http\Resources\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'email' => $this->email,
            'avatar' => $this->avatar,
            'is_active' => (bool) $this->is_active,
            'is_verified' => !is_null($this->email_verified_at),
            'requires_email_verification' => $this->requiresEmailVerification(),
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
            'roles' => $this->roles->pluck('slug')->toArray(),
            'permissions' => $this->permissions->pluck('slug')->merge(
                $this->roles->flatMap(fn($role) => $role->permissions->pluck('slug'))
            )->unique()->values()->toArray(),
            'created_at' => $this->created_at?->toIso8601String(),
            'academy_onboarding_completed_at' => $this->academy_onboarding_completed_at?->toIso8601String(),
        ];
    }
}
