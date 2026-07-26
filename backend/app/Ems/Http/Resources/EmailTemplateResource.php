<?php

namespace App\Ems\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Ems\Models\EmailTemplate */
class EmailTemplateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'key' => $this->key,
            'name' => $this->name,
            'category' => $this->category,
            'subject' => $this->subject,
            'body_html' => $this->body_html,
            'body_text' => $this->body_text,
            'placeholders' => $this->placeholders ?? [],
            'is_active' => $this->is_active,
            'is_system' => $this->is_system,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
