<?php

namespace App\Ems\Http\Controllers\V1;

use App\Ems\Http\Controllers\EmsController;
use App\Ems\Models\Event;
use App\Ems\Models\TicketType;
use App\Ems\Models\PromoCode;
use App\Ems\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PromoCodeController extends EmsController
{
    /**
     * GET /api/v1/ems/promo-codes
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PromoCode::class);

        // Include usage statistics: times used, revenue impact
        $promoCodes = PromoCode::whereNull('archived_at')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($code) {
                $usedCount = $code->registrations()->whereIn('status', ['confirmed', 'pending', 'awaiting_payment'])->count();
                $revenueImpact = (float) $code->registrations()->whereIn('status', ['confirmed', 'pending', 'awaiting_payment'])->sum('discount_amount');

                return array_merge($code->toArray(), [
                    'times_used' => $usedCount,
                    'remaining_uses' => $code->usage_limit !== null ? max(0, $code->usage_limit - $usedCount) : null,
                    'revenue_impact' => $revenueImpact,
                ]);
            });

        return ApiResponse::success($promoCodes, 'Promo codes retrieved.');
    }

    /**
     * POST /api/v1/ems/promo-codes
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', PromoCode::class);

        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:ems_promo_codes,code',
            'description' => 'nullable|string|max:255',
            'discount_type' => 'required|string|in:percentage,fixed,free',
            'discount_value' => 'required|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'usage_per_attendee' => 'required|integer|min:1',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'minimum_purchase' => 'nullable|numeric|min:0',
            'is_active' => 'boolean',
            'eligible_events' => 'nullable|array', // list of event IDs/UUIDs
            'eligible_ticket_types' => 'nullable|array', // list of ticket type IDs/UUIDs
        ]);

        $promo = PromoCode::create(array_merge([
            'uuid' => (string) Str::uuid(),
        ], collect($validated)->except(['eligible_events', 'eligible_ticket_types'])->toArray()));

        // Sync event eligibility if specified
        if (!empty($validated['eligible_events'])) {
            $eventIds = Event::whereIn('uuid', $validated['eligible_events'])->pluck('id');
            $promo->events()->sync($eventIds);
        }

        // Sync ticket type eligibility if specified
        if (!empty($validated['eligible_ticket_types'])) {
            $ticketIds = TicketType::whereIn('uuid', $validated['eligible_ticket_types'])->pluck('id');
            $promo->ticketTypes()->sync($ticketIds);
        }

        return ApiResponse::created($promo, 'Promo code created.');
    }

    /**
     * GET /api/v1/ems/promo-codes/{promoCode}
     */
    public function show(PromoCode $promoCode): JsonResponse
    {
        $this->authorize('view', $promoCode);

        $promoCode->load(['events', 'ticketTypes']);

        $usedCount = $promoCode->registrations()->whereIn('status', ['confirmed', 'pending', 'awaiting_payment'])->count();
        $revenueImpact = (float) $promoCode->registrations()->whereIn('status', ['confirmed', 'pending', 'awaiting_payment'])->sum('discount_amount');

        $data = array_merge($promoCode->toArray(), [
            'times_used' => $usedCount,
            'remaining_uses' => $promoCode->usage_limit !== null ? max(0, $promoCode->usage_limit - $usedCount) : null,
            'revenue_impact' => $revenueImpact,
        ]);

        return ApiResponse::success($data, 'Promo code details.');
    }

    /**
     * PUT /api/v1/ems/promo-codes/{promoCode}
     */
    public function update(Request $request, PromoCode $promoCode): JsonResponse
    {
        $this->authorize('update', $promoCode);

        $validated = $request->validate([
            'description' => 'nullable|string|max:255',
            'usage_limit' => 'nullable|integer|min:1',
            'usage_per_attendee' => 'required|integer|min:1',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'minimum_purchase' => 'nullable|numeric|min:0',
            'is_active' => 'boolean',
            'eligible_events' => 'nullable|array',
            'eligible_ticket_types' => 'nullable|array',
        ]);

        $promoCode->update(collect($validated)->except(['eligible_events', 'eligible_ticket_types'])->toArray());

        if (isset($validated['eligible_events'])) {
            $eventIds = Event::whereIn('uuid', $validated['eligible_events'])->pluck('id');
            $promoCode->events()->sync($eventIds);
        }

        if (isset($validated['eligible_ticket_types'])) {
            $ticketIds = TicketType::whereIn('uuid', $validated['eligible_ticket_types'])->pluck('id');
            $promoCode->ticketTypes()->sync($ticketIds);
        }

        return ApiResponse::success($promoCode, 'Promo code updated.');
    }

    /**
     * DELETE /api/v1/ems/promo-codes/{promoCode}
     */
    public function destroy(PromoCode $promoCode): JsonResponse
    {
        $this->authorize('delete', $promoCode);

        $promoCode->update(['archived_at' => now(), 'is_active' => false]);

        return ApiResponse::success(null, 'Promo code archived.');
    }

    /**
     * POST /api/v1/ems/promo-codes/validate
     * and POST /api/v1/ems/public/promo-codes/validate
     */
    public function validateCode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string',
            'event_uuid' => 'required|uuid',
            'ticket_type_uuid' => 'nullable|uuid',
            'email' => 'nullable|email',
            'amount' => 'nullable|numeric|min:0',
        ]);

        $promo = PromoCode::where('code', strtoupper($validated['code']))
            ->whereNull('archived_at')
            ->first();

        if (!$promo) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid promo code.',
            ], 422);
        }

        $event = Event::where('uuid', $validated['event_uuid'])->firstOrFail();
        $ticketType = !empty($validated['ticket_type_uuid'])
            ? TicketType::where('uuid', $validated['ticket_type_uuid'])->firstOrFail()
            : null;

        $check = $promo->isValidFor(
            $event,
            $ticketType,
            $request->user(),
            (float) ($validated['amount'] ?? 0.0),
            $validated['email'] ?? null
        );

        if (!$check['valid']) {
            return response()->json([
                'success' => false,
                'message' => $check['reason'],
            ], 422);
        }

        return ApiResponse::success([
            'valid' => true,
            'code' => $promo->code,
            'discount_type' => $promo->discount_type,
            'discount_value' => (float) $promo->discount_value,
            'discount_amount' => (float) $promo->calculateDiscount((float) ($validated['amount'] ?? 0.0)),
        ], 'Promo code is valid.');
    }
}
