<?php

namespace App\Mlibms\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Mlibms\Http\Resources\MemberResource;
use App\Mlibms\Models\Member;
use App\Mlibms\Services\MemberService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminMemberController extends Controller
{
    public function __construct(
        protected MemberService $memberService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Member::query();

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('search')) {
            $term = $request->input('search');
            $query->where(function ($q) use ($term) {
                $q->where('name', 'LIKE', "%{$term}%")
                  ->orWhere('email', 'LIKE', "%{$term}%")
                  ->orWhere('library_card_number', 'LIKE', "%{$term}%");
            });
        }

        $members = $query->orderBy('registered_at', 'desc')->paginate($request->input('per_page', 20));

        return response()->json([
            'data' => MemberResource::collection($members),
            'meta' => [
                'current_page' => $members->currentPage(),
                'last_page' => $members->lastPage(),
                'per_page' => $members->perPage(),
                'total' => $members->total(),
            ],
        ]);
    }

    public function storeGuest(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:50',
            'max_active_loans' => 'nullable|integer|min:1|max:10',
            'notes' => 'nullable|string',
        ]);

        $guest = $this->memberService->createGuestMember($data);

        return response()->json([
            'message' => 'Walk-in guest borrower registered successfully.',
            'data' => new MemberResource($guest),
        ], 201);
    }

    public function update(Request $request, string $uuid): JsonResponse
    {
        $member = Member::where('uuid', $uuid)->firstOrFail();

        $data = $request->validate([
            'status' => 'nullable|string|in:active,suspended,expired',
            'max_active_loans' => 'nullable|integer|min:1|max:10',
            'notes' => 'nullable|string',
            'suspension_reason' => 'nullable|string',
        ]);

        if (isset($data['status'])) {
            if ($data['status'] === 'suspended' && $member->status !== 'suspended') {
                $data['suspended_at'] = now();
            } elseif ($data['status'] === 'active') {
                $data['suspended_at'] = null;
                $data['suspension_reason'] = null;
            }
        }

        $member->update(array_filter($data, fn($v) => !is_null($v)));

        return response()->json([
            'message' => 'Member record updated successfully.',
            'data' => new MemberResource($member),
        ]);
    }
}
