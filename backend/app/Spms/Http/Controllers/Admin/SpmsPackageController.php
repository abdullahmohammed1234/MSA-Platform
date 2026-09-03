<?php

namespace App\Spms\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Spms\Models\Opportunity;
use App\Spms\Models\Package;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class SpmsPackageController extends Controller
{
    public function store(Request $request, string $opportunityUuid): JsonResponse
    {
        Gate::authorize('create', Opportunity::class);

        $opportunity = Opportunity::where('uuid', $opportunityUuid)->firstOrFail();

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'price_cents' => 'required|integer|min:0',
            'max_available' => 'nullable|integer|min:1',
            'is_customizable' => 'nullable|boolean',
            'is_public' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
            'benefits' => 'nullable|array',
            'benefits.*.title' => 'required|string|max:255',
            'benefits.*.description' => 'nullable|string',
            'benefits.*.deliverable_type' => 'nullable|string|max:50',
            'benefits.*.quantity' => 'nullable|integer|min:1',
        ]);

        $package = DB::transaction(function () use ($opportunity, $validated) {
            $benefits = $validated['benefits'] ?? [];
            unset($validated['benefits']);

            $validated['opportunity_id'] = $opportunity->id;
            $pkg = Package::create($validated);

            foreach ($benefits as $b) {
                $pkg->benefits()->create($b);
            }

            return $pkg->load('benefits');
        });

        return response()->json([
            'success' => true,
            'message' => 'Package created successfully.',
            'data' => $package,
        ], 201);
    }

    public function update(Request $request, string $uuid): JsonResponse
    {
        Gate::authorize('update', Opportunity::class);

        $package = Package::where('uuid', $uuid)->firstOrFail();

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:100',
            'description' => 'nullable|string',
            'price_cents' => 'sometimes|required|integer|min:0',
            'max_available' => 'nullable|integer|min:1',
            'is_customizable' => 'nullable|boolean',
            'is_public' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
            'benefits' => 'nullable|array',
            'benefits.*.title' => 'required|string|max:255',
            'benefits.*.description' => 'nullable|string',
            'benefits.*.deliverable_type' => 'nullable|string|max:50',
            'benefits.*.quantity' => 'nullable|integer|min:1',
        ]);

        $updated = DB::transaction(function () use ($package, $validated) {
            if (array_key_exists('benefits', $validated)) {
                $benefits = $validated['benefits'];
                unset($validated['benefits']);
                $package->benefits()->delete();
                foreach ($benefits as $b) {
                    $package->benefits()->create($b);
                }
            }

            $package->update($validated);
            return $package->load('benefits');
        });

        return response()->json([
            'success' => true,
            'message' => 'Package updated successfully.',
            'data' => $updated,
        ]);
    }

    public function destroy(string $uuid): JsonResponse
    {
        Gate::authorize('delete', Opportunity::class);

        $package = Package::where('uuid', $uuid)->firstOrFail();
        $package->delete();

        return response()->json([
            'success' => true,
            'message' => 'Package deleted successfully.',
        ]);
    }
}
