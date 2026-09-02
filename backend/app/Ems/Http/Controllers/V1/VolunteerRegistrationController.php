<?php

namespace App\Ems\Http\Controllers\V1;

use App\Enums\VolunteerRegistrationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateVolunteerRegistrationRequest;
use App\Http\Resources\VolunteerRegistrationResource;
use App\Models\VolunteerRegistration;
use App\Services\VolunteerRegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class VolunteerRegistrationController extends Controller
{
    protected VolunteerRegistrationService $service;

    public function __construct(VolunteerRegistrationService $service)
    {
        $this->service = $service;
    }

    /**
     * Display a paginated, searchable, filterable list of volunteer registrations.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', VolunteerRegistration::class);

        $query = VolunteerRegistration::query()->with('assignedTo');

        if ($request->filled('search')) {
            $search = trim((string) $request->query('search'));
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('student_number', 'like', "%{$search}%")
                    ->orWhere('department', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $status = (string) $request->query('status');
            if ($status !== 'all' && VolunteerRegistrationStatus::tryFrom($status) !== null) {
                $query->where('status', $status);
            }
        }

        $sortBy = (string) $request->query('sort_by', 'created_at');
        $sortOrder = strtolower((string) $request->query('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc';

        $allowedSorts = ['created_at', 'name', 'email', 'status', 'department'];
        if (in_array($sortBy, $allowedSorts, true)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $perPage = min(max((int) $request->query('per_page', 15), 1), 100);

        return VolunteerRegistrationResource::collection($query->paginate($perPage));
    }

    /**
     * Display single volunteer registration detail.
     */
    public function show(VolunteerRegistration $volunteerRegistration): VolunteerRegistrationResource
    {
        $this->authorize('view', $volunteerRegistration);

        $volunteerRegistration->load('assignedTo');

        return new VolunteerRegistrationResource($volunteerRegistration);
    }

    /**
     * Update administrative fields (status, admin_notes, assigned_to).
     */
    public function update(UpdateVolunteerRegistrationRequest $request, VolunteerRegistration $volunteerRegistration): VolunteerRegistrationResource
    {
        $this->authorize('update', $volunteerRegistration);

        $updated = $this->service->update(
            $volunteerRegistration,
            $request->validated(),
            $request->user()?->id
        );

        return new VolunteerRegistrationResource($updated);
    }

    /**
     * Soft delete / archive a volunteer registration.
     */
    public function destroy(Request $request, VolunteerRegistration $volunteerRegistration): JsonResponse
    {
        $this->authorize('delete', $volunteerRegistration);

        $this->service->delete($volunteerRegistration, $request->user()?->id);

        return response()->json([
            'success' => true,
            'message' => 'Volunteer registration archived successfully.',
        ]);
    }
}
