<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SimulationSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SimulationController extends Controller
{
    public function history(Request $request): JsonResponse
    {
        $sessions = SimulationSession::where('user_id', $request->user()->id)
            ->orderByDesc('completed_at')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (SimulationSession $session) => $this->formatSession($session));

        $totalXp = $sessions->sum('overallScore');
        $count = $sessions->count();

        return response()->json([
            'success' => true,
            'sessions' => $sessions,
            'summary' => [
                'totalXp' => $totalXp,
                'avgScore' => $count > 0 ? (int) round($totalXp / $count) : 0,
                'avgAtmosphere' => $count > 0
                    ? (int) round($sessions->avg('atmosphereScore'))
                    : 0,
                'attemptCount' => $count,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'scenario_id' => 'required|string|max:128',
            'scenario_title' => 'required|string|max:255',
            'category' => 'nullable|string|max:128',
            'difficulty' => 'nullable|string|max:64',
            'character_name' => 'nullable|string|max:128',
            'avatar_seed' => 'nullable|string|max:64',
            'overall_score' => 'required|integer|min:0|max:10000',
            'atmosphere_score' => 'required|integer|min:0|max:100',
            'transcript' => 'nullable|array',
            'reflections' => 'nullable|array',
            'completed_at' => 'nullable|date',
        ]);

        $session = SimulationSession::create([
            'user_id' => $request->user()->id,
            'scenario_id' => $validated['scenario_id'],
            'scenario_title' => $validated['scenario_title'],
            'category' => $validated['category'] ?? null,
            'difficulty' => $validated['difficulty'] ?? null,
            'character_name' => $validated['character_name'] ?? null,
            'avatar_seed' => $validated['avatar_seed'] ?? null,
            'overall_score' => $validated['overall_score'],
            'atmosphere_score' => $validated['atmosphere_score'],
            'transcript' => $validated['transcript'] ?? [],
            'reflections' => $validated['reflections'] ?? [],
            'is_bookmarked' => false,
            'completed_at' => $validated['completed_at'] ?? now(),
        ]);

        return response()->json([
            'success' => true,
            'session' => $this->formatSession($session),
        ], 201);
    }

    public function updateBookmark(Request $request, SimulationSession $session): JsonResponse
    {
        if ($session->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        $validated = $request->validate([
            'is_bookmarked' => 'required|boolean',
        ]);

        $session->update(['is_bookmarked' => $validated['is_bookmarked']]);

        return response()->json([
            'success' => true,
            'session' => $this->formatSession($session->fresh()),
        ]);
    }

    private function formatSession(SimulationSession $session): array
    {
        return [
            'id' => (string) $session->id,
            'scenarioId' => $session->scenario_id,
            'scenarioTitle' => $session->scenario_title,
            'category' => $session->category,
            'difficulty' => $session->difficulty,
            'characterName' => $session->character_name,
            'avatarSeed' => $session->avatar_seed,
            'overallScore' => $session->overall_score,
            'atmosphereScore' => $session->atmosphere_score,
            'transcript' => $session->transcript ?? [],
            'reflections' => $session->reflections ?? [],
            'isBookmarked' => $session->is_bookmarked,
            'completedAt' => $session->completed_at?->toIso8601String(),
        ];
    }

    public function scenarios(): JsonResponse
    {
        $path = database_path('data/practice_scenarios.json');
        if (!file_exists($path)) {
            return response()->json([
                'success' => true,
                'scenarios' => [],
            ]);
        }

        $payload = json_decode(file_get_contents($path), true);
        $scenarios = is_array($payload) ? $payload : [];

        return response()->json([
            'success' => true,
            'scenarios' => $scenarios,
        ]);
    }

    public function scenario(string $scenarioId): JsonResponse
    {
        $path = database_path('data/practice_scenarios.json');
        if (!file_exists($path)) {
            return response()->json(['success' => false, 'message' => 'Scenario catalog unavailable.'], 404);
        }

        $scenarios = json_decode(file_get_contents($path), true) ?: [];
        $match = collect($scenarios)->firstWhere('id', $scenarioId);

        if (!$match) {
            return response()->json(['success' => false, 'message' => 'Scenario not found.'], 404);
        }

        return response()->json([
            'success' => true,
            'scenario' => $match,
        ]);
    }
}
