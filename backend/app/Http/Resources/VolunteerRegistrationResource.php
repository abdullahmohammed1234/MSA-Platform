<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VolunteerRegistrationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->uuid,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'student_number' => $this->student_number,
            'department' => $this->department,
            'interests' => $this->interests,
            'experience' => $this->experience,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];

        // Include administrative information for authorized admin requests
        if ($request->user() !== null) {
            $data['status'] = $this->status->value ?? (string) $this->status;
            $data['status_label'] = method_exists($this->status, 'label') ? $this->status->label() : (string) $this->status;
            $data['admin_notes'] = $this->admin_notes;
            $data['assigned_to'] = $this->assigned_to;
            $data['assigned_user'] = $this->whenLoaded('assignedTo', function () {
                return $this->assignedTo ? [
                    'id' => $this->assignedTo->id,
                    'name' => $this->assignedTo->name,
                    'email' => $this->assignedTo->email,
                ] : null;
            });
            $data['contacted_at'] = $this->contacted_at?->toIso8601String();
            $data['processed_at'] = $this->processed_at?->toIso8601String();
        }

        return $data;
    }
}
