<?php

namespace App\Ems\Http\Resources;

use App\Ems\Models\EventDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EventDocument
 */
class EventDocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'event_id' => $this->event_id,
            'event_uuid' => $this->event?->uuid,
            'name' => $this->name,
            'document_type' => $this->document_type,
            'description' => $this->description,
            'original_filename' => $this->original_filename,
            'mime_type' => $this->mime_type,
            'file_size' => (int) $this->file_size,
            'is_active' => (bool) $this->is_active,
            'sort_order' => (int) $this->sort_order,
            'uploaded_by' => $this->uploaded_by,
            'uploader_name' => $this->uploader?->name,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
