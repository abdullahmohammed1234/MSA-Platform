<?php

namespace App\Ems\Http\Resources;

use App\Ems\Services\EmsActivityLogger;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * An EMS audit entry rendered for the dashboard activity feed.
 *
 * The raw audit row carries an IP address and user agent; neither is exposed
 * here, because the dashboard is a general operational screen rather than a
 * security console.
 *
 * @mixin AuditLog
 */
class ActivityLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => Str::after($this->action, EmsActivityLogger::PREFIX),
            'description' => $this->description,
            'result' => data_get($this->payload, 'result', EmsActivityLogger::RESULT_SUCCESS),
            'subject_type' => $this->target_type ? class_basename($this->target_type) : null,
            'subject_id' => $this->target_id,
            'actor' => new EmsUserSummaryResource($this->whenLoaded('user')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
