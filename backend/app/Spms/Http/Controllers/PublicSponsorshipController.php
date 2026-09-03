<?php

namespace App\Spms\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Spms\Models\Opportunity;
use App\Spms\Models\Organization;
use App\Spms\Models\Package;
use App\Spms\Services\OrganizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicSponsorshipController extends Controller
{
    public function __construct(private readonly OrganizationService $organizationService) {}

    public function index(): JsonResponse
    {
        $opportunities = Opportunity::where('is_public', true)
            ->where('status', 'active')
            ->with(['event', 'packages' => function ($q) {
                $q->where('is_public', true)->orderBy('sort_order')->with('benefits');
            }])
            ->orderBy('created_at', 'desc')
            ->get();

        $featuredPartners = Organization::where('is_publicly_featured', true)
            ->where('status', 'active')
            ->select(['uuid', 'display_name', 'industry', 'website_url', 'logo_url', 'relationship_type'])
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'opportunities' => $opportunities,
                'featured_partners' => $featuredPartners,
            ],
        ]);
    }

    public function showOpportunity(string $slug): JsonResponse
    {
        $opportunity = Opportunity::where('slug', $slug)
            ->where('is_public', true)
            ->with(['event', 'packages' => function ($q) {
                $q->where('is_public', true)->orderBy('sort_order')->with('benefits');
            }])
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $opportunity,
        ]);
    }

    public function inquire(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'organization_name' => 'required|string|max:255',
            'contact_name' => 'required|string|max:255',
            'contact_email' => 'required|email|max:255',
            'contact_phone' => 'nullable|string|max:50',
            'opportunity_id' => 'nullable|exists:spms_opportunities,id',
            'package_id' => 'nullable|exists:spms_packages,id',
            'relationship_type' => 'nullable|string|max:50',
            'estimated_budget_cents' => 'nullable|integer|min:0',
            'message' => 'required|string|max:2000',
        ]);

        $nameParts = explode(' ', trim($validated['contact_name']), 2);
        $firstName = $nameParts[0];
        $lastName = $nameParts[1] ?? '';

        $organization = $this->organizationService->createOrganization([
            'legal_name' => $validated['organization_name'],
            'display_name' => $validated['organization_name'],
            'email' => $validated['contact_email'],
            'phone' => $validated['contact_phone'] ?? null,
            'relationship_type' => $validated['relationship_type'] ?? 'sponsor',
            'status' => 'prospect',
            'notes' => "Public Inquiry Message:\n" . $validated['message'],
        ]);

        $this->organizationService->addContact($organization, [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $validated['contact_email'],
            'phone' => $validated['contact_phone'] ?? null,
            'is_primary' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Thank you for your sponsorship inquiry! Our partnership team will contact you shortly.',
        ], 201);
    }
}
