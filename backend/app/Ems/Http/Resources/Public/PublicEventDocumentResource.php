<?php

namespace App\Ems\Http\Resources\Public;

use App\Ems\Models\EventDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EventDocument
 */
class PublicEventDocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'document_type' => $this->document_type,
            'description' => $this->description,
            'original_filename' => $this->original_filename,
            'mime_type' => $this->mime_type,
            'file_size' => (int) $this->file_size,
            'sort_order' => (int) $this->sort_order,
        ];
    }
}
