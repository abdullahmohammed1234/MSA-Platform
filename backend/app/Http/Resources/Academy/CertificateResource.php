<?php

namespace App\Http\Resources\Academy;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CertificateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'title' => $this->title,
            'description' => $this->description,
            'template' => $this->certificate && $this->certificate->template ? $this->certificate->template->name : 'Standard',
            'code' => $this->code,
            'verification_token' => $this->verification_token,
            'pdf_path' => $this->pdf_path,
            'issued_at' => $this->issued_at->toIso8601String(),
            'type' => $this->certificate ? $this->certificate->type : 'course',
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                ];
            }),
            'course' => $this->when($this->certificate && $this->certificate->course, function () {
                return [
                    'id' => $this->certificate->course->id,
                    'title' => $this->certificate->course->title,
                    'slug' => $this->certificate->course->slug,
                ];
            }),
            'learning_path' => $this->when($this->certificate && $this->certificate->learningPath, function () {
                return [
                    'id' => $this->certificate->learningPath->id,
                    'title' => $this->certificate->learningPath->title,
                    'slug' => $this->certificate->learningPath->slug,
                ];
            }),
        ];
    }
}
