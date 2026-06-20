<?php

namespace App\Http\Controllers\Admin\CMS;

use App\Http\Controllers\Controller;
use App\Http\Requests\CMS\SaveTeamMemberRequest;
use App\Models\CMS\TeamMember;
use App\Services\CMS\TeamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class TeamController extends Controller
{
    protected $service;

    public function __construct(TeamService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['search', 'dept', 'status']);
        $members = $this->service->list($filters, $request->input('per_page', 15));

        return response()->json($members);
    }

    public function store(SaveTeamMemberRequest $request): JsonResponse
    {
        $member = $this->service->create($request->validated(), Auth::id());
        Cache::forget('website_team');

        return response()->json([
            'success' => true,
            'message' => 'Team member added successfully.',
            'member' => $member
        ], 201);
    }

    public function show(string $uuid): JsonResponse
    {
        $member = $this->service->findByUuid($uuid);
        if (!$member) {
            return response()->json(['message' => 'Team member not found.'], 404);
        }

        return response()->json($member);
    }

    public function update(SaveTeamMemberRequest $request, string $uuid): JsonResponse
    {
        $member = $this->service->findByUuid($uuid);
        if (!$member) {
            return response()->json(['message' => 'Team member not found.'], 404);
        }

        $this->service->update($member, $request->validated(), Auth::id());
        Cache::forget('website_team');

        return response()->json([
            'success' => true,
            'message' => 'Team member updated successfully.',
            'member' => $member
        ]);
    }

    public function destroy(string $uuid): JsonResponse
    {
        $member = $this->service->findByUuid($uuid);
        if (!$member) {
            return response()->json(['message' => 'Team member not found.'], 404);
        }

        $this->service->delete($member, Auth::id());
        Cache::forget('website_team');

        return response()->json([
            'success' => true,
            'message' => 'Team member deleted successfully.'
        ]);
    }

    public function reorder(Request $request): JsonResponse
    {
        $request->validate([
            'uuids' => 'required|array',
            'uuids.*' => 'string|uuid'
        ]);

        $this->service->reorder($request->input('uuids'), Auth::id());
        Cache::forget('website_team');

        return response()->json([
            'success' => true,
            'message' => 'Team order updated successfully.'
        ]);
    }

    public function revisions(string $uuid): JsonResponse
    {
        $member = $this->service->findByUuid($uuid);
        if (!$member) {
            return response()->json(['message' => 'Team member not found.'], 404);
        }

        return response()->json([
            'revisions' => $this->service->getRevisions($member)
        ]);
    }

    public function rollback(Request $request, string $uuid): JsonResponse
    {
        $request->validate(['version' => 'required|integer']);

        $member = $this->service->findByUuid($uuid);
        if (!$member) {
            return response()->json(['message' => 'Team member not found.'], 404);
        }

        $rolledBack = $this->service->rollback($member, $request->input('version'), Auth::id());

        if (!$rolledBack) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to rollback. Version not found.'
            ], 400);
        }

        Cache::forget('website_team');

        return response()->json([
            'success' => true,
            'message' => 'Team member rolled back successfully.',
            'member' => $member->fresh()
        ]);
    }
}
