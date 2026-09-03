<?php

namespace App\Spms\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Spms\Models\Communication;
use App\Spms\Models\FollowUp;
use App\Spms\Models\Organization;
use App\Spms\Models\Renewal;
use App\Spms\Models\Sponsorship;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SpmsCommunicationController extends Controller
{
    public function logCommunication(Request $request, string $organizationUuid): JsonResponse
    {
        Gate::authorize('update', Organization::class);

        $organization = Organization::where('uuid', $organizationUuid)->firstOrFail();

        $validated = $request->validate([
            'sponsorship_id' => 'nullable|exists:spms_sponsorships,id',
            'contact_id' => 'nullable|exists:spms_contacts,id',
            'interaction_type' => 'required|string|max:50',
            'subject' => 'required|string|max:255',
            'body' => 'required|string',
            'interaction_at' => 'nullable|date',
        ]);

        $communication = $organization->communications()->create([
            'sponsorship_id' => $validated['sponsorship_id'] ?? null,
            'contact_id' => $validated['contact_id'] ?? null,
            'logged_by' => $request->user()->id,
            'interaction_type' => $validated['interaction_type'],
            'subject' => $validated['subject'],
            'body' => $validated['body'],
            'interaction_at' => $validated['interaction_at'] ?? now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Communication logged successfully.',
            'data' => $communication->load('logger'),
        ], 201);
    }

    public function createFollowUp(Request $request, string $organizationUuid): JsonResponse
    {
        Gate::authorize('update', Organization::class);

        $organization = Organization::where('uuid', $organizationUuid)->firstOrFail();

        $validated = $request->validate([
            'sponsorship_id' => 'nullable|exists:spms_sponsorships,id',
            'assigned_to' => 'required|exists:users,id',
            'title' => 'required|string|max:255',
            'due_date' => 'required|date',
        ]);

        $followUp = $organization->followUps()->create([
            'sponsorship_id' => $validated['sponsorship_id'] ?? null,
            'assigned_to' => $validated['assigned_to'],
            'title' => $validated['title'],
            'due_date' => $validated['due_date'],
            'status' => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Follow-up task created.',
            'data' => $followUp->load('assignee'),
        ], 201);
    }

    public function completeFollowUp(Request $request, int $id): JsonResponse
    {
        Gate::authorize('update', Organization::class);

        $followUp = FollowUp::findOrFail($id);
        $followUp->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Follow-up marked completed.',
            'data' => $followUp,
        ]);
    }

    public function renewals(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Sponsorship::class);

        $renewals = Renewal::with(['previousSponsorship.organization', 'newSponsorship', 'owner'])
            ->orderBy('target_renewal_date', 'asc')
            ->paginate($request->input('per_page', 25));

        return response()->json([
            'success' => true,
            'data' => $renewals->items(),
            'meta' => [
                'current_page' => $renewals->currentPage(),
                'last_page' => $renewals->lastPage(),
                'per_page' => $renewals->perPage(),
                'total' => $renewals->total(),
            ],
        ]);
    }
}
