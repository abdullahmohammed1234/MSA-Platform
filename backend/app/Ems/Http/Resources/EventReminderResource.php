<?php

namespace App\Ems\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Ems\Models\EventReminder */
class EventReminderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'label' => $this->displayLabel(),
            'offset_value' => $this->offset_value,
            'offset_unit' => $this->offset_unit?->value ?? $this->offset_unit,
            'enabled' => $this->enabled,
            'template_key' => $this->template_key,
            'audience' => $this->audience?->value ?? $this->audience,
            'next_run_at' => $this->next_run_at?->toIso8601String(),
            'last_run_at' => $this->last_run_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
