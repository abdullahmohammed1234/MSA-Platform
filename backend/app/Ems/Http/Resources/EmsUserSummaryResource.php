<?php

namespace App\Ems\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The minimum a client needs to render a person: no email verification state,
 * no roles, no activity timestamps.
 *
 * Used wherever a user appears as a related record (organizer, creator, actor
 * on an activity entry) so those contexts never over-expose account data.
 *
 * @mixin User
 */
class EmsUserSummaryResource extends JsonResource
{
    /**
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
        ];
    }
}
