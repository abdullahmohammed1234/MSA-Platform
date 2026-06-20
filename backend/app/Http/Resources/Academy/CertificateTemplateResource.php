<?php

namespace App\Http\Resources\Academy;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CertificateTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'title_template' => $this->title_template,
            'description_template' => $this->description_template,
            'layout' => $this->layout,
            'branding' => $this->branding,
            'signatures' => $this->signatures,
            'background_asset' => $this->background_asset,
            'status' => $this->status,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
