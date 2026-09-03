<?php

namespace App\Spms\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Spms\Models\Contact;
use App\Spms\Models\Organization;
use App\Spms\Services\OrganizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SpmsContactController extends Controller
{
    public function __construct(private readonly OrganizationService $organizationService) {}

    public function store(Request $request, string $organizationUuid): JsonResponse
    {
        Gate::authorize('create', Organization::class);

        $organization = Organization::where('uuid', $organizationUuid)->firstOrFail();

        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'title' => 'nullable|string|max:100',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:50',
            'is_primary' => 'nullable|boolean',
            'preferred_contact_method' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
        ]);

        $contact = $this->organizationService->addContact($organization, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Contact added successfully.',
            'data' => $contact,
        ], 201);
    }

    public function update(Request $request, string $uuid): JsonResponse
    {
        Gate::authorize('update', Organization::class);

        $contact = Contact::where('uuid', $uuid)->firstOrFail();

        $validated = $request->validate([
            'first_name' => 'sometimes|required|string|max:100',
            'last_name' => 'sometimes|required|string|max:100',
            'title' => 'nullable|string|max:100',
            'email' => 'sometimes|required|email|max:255',
            'phone' => 'nullable|string|max:50',
            'is_primary' => 'nullable|boolean',
            'preferred_contact_method' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
        ]);

        if (!empty($validated['is_primary']) && $validated['is_primary'] === true) {
            Contact::where('organization_id', $contact->organization_id)->update(['is_primary' => false]);
        }

        $contact->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Contact updated successfully.',
            'data' => $contact,
        ]);
    }

    public function destroy(string $uuid): JsonResponse
    {
        Gate::authorize('delete', Organization::class);

        $contact = Contact::where('uuid', $uuid)->firstOrFail();
        $contact->delete();

        return response()->json([
            'success' => true,
            'message' => 'Contact removed successfully.',
        ]);
    }
}
