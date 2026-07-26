<?php

namespace App\Ems\Http\Controllers\V1;

use App\Ems\Http\Controllers\EmsController;
use App\Ems\Models\EventTemplate;
use App\Ems\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class EventTemplateController extends EmsController
{
    /**
     * GET /api/v1/ems/event-templates
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', EventTemplate::class);

        $templates = EventTemplate::with('category')
            ->whereNull('archived_at')
            ->orderBy('is_default', 'desc')
            ->orderBy('name', 'asc')
            ->get();

        return ApiResponse::success($templates, 'Event templates retrieved.');
    }

    /**
     * POST /api/v1/ems/event-templates
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', EventTemplate::class);

        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:ems_event_templates,name',
            'description' => 'nullable|string',
            'category_id' => 'nullable|integer|exists:ems_event_categories,id',
            'capacity' => 'nullable|integer|min:0',
            'is_public' => 'boolean',
            'waitlist_enabled' => 'boolean',
            'max_tickets_per_order' => 'nullable|integer|min:1',
            'max_registrations_per_attendee' => 'nullable|integer|min:1',
            'registration_deadline_offset_days' => 'nullable|integer|min:0',
            'settings' => 'nullable|array',
            'is_default' => 'boolean',
        ]);

        if (!empty($validated['is_default'])) {
            EventTemplate::where('is_default', true)->update(['is_default' => false]);
        }

        $template = EventTemplate::create(array_merge([
            'uuid' => (string) Str::uuid(),
        ], $validated));

        return ApiResponse::created($template, 'Event template created.');
    }

    /**
     * GET /api/v1/ems/event-templates/{template}
     */
    public function show(EventTemplate $template): JsonResponse
    {
        $this->authorize('view', $template);

        $template->load('category');

        return ApiResponse::success($template, 'Event template retrieved.');
    }

    /**
     * PUT /api/v1/ems/event-templates/{template}
     */
    public function update(Request $request, EventTemplate $template): JsonResponse
    {
        $this->authorize('update', $template);

        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:ems_event_templates,name,' . $template->id,
            'description' => 'nullable|string',
            'category_id' => 'nullable|integer|exists:ems_event_categories,id',
            'capacity' => 'nullable|integer|min:0',
            'is_public' => 'boolean',
            'waitlist_enabled' => 'boolean',
            'max_tickets_per_order' => 'nullable|integer|min:1',
            'max_registrations_per_attendee' => 'nullable|integer|min:1',
            'registration_deadline_offset_days' => 'nullable|integer|min:0',
            'settings' => 'nullable|array',
            'is_default' => 'boolean',
        ]);

        if (!empty($validated['is_default']) && !$template->is_default) {
            EventTemplate::where('is_default', true)->update(['is_default' => false]);
        }

        $template->update($validated);

        return ApiResponse::success($template, 'Event template updated.');
    }

    /**
     * DELETE /api/v1/ems/event-templates/{template}
     */
    public function destroy(EventTemplate $template): JsonResponse
    {
        $this->authorize('delete', $template);

        // Soft archive instead of direct delete if we want historical integrity
        $template->update(['archived_at' => now()]);

        return ApiResponse::success(null, 'Event template deleted successfully.');
    }

    /**
     * POST /api/v1/ems/event-templates/{template}/duplicate
     */
    public function duplicate(EventTemplate $template): JsonResponse
    {
        $this->authorize('duplicate', $template);

        $duplicate = $template->replicate();
        $duplicate->uuid = (string) Str::uuid();
        $duplicate->name = $template->name . ' (Copy)';
        $duplicate->is_default = false;
        $duplicate->save();

        return ApiResponse::created($duplicate, 'Event template duplicated.');
    }

    /**
     * POST /api/v1/ems/event-templates/{template}/default
     */
    public function setDefault(EventTemplate $template): JsonResponse
    {
        $this->authorize('setDefault', $template);

        EventTemplate::where('is_default', true)->update(['is_default' => false]);
        $template->update(['is_default' => true]);

        return ApiResponse::success($template, 'Event template set as default.');
    }
}
