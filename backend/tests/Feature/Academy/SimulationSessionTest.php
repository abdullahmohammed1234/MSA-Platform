<?php

namespace Tests\Feature\Academy;

use App\Models\SimulationSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SimulationSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_simulation_history_is_rejected(): void
    {
        $this->getJson('/api/v1/simulations/history')->assertStatus(401);
    }

    public function test_user_can_store_and_fetch_simulation_history(): void
    {
        $user = $this->createVolunteerUser();

        $this->actingAs($user)
            ->postJson('/api/v1/simulations/sessions', [
                'scenario_id' => 'booth-suffering',
                'scenario_title' => 'Mercy and the Problem of Pain',
                'category' => 'Emotional Conversations',
                'difficulty' => 'Beginner',
                'character_name' => 'Sarah',
                'avatar_seed' => 'sarah',
                'overall_score' => 280,
                'atmosphere_score' => 85,
                'transcript' => [
                    [
                        'nodeId' => 'evil_intro',
                        'inquiry' => 'Why is there suffering?',
                        'selectedResponse' => 'Validate empathy first.',
                        'score' => 100,
                        'mentorAdvice' => 'Excellent empathy.',
                    ],
                ],
                'reflections' => [],
            ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('session.scenarioId', 'booth-suffering');

        $this->actingAs($user)
            ->getJson('/api/v1/simulations/history')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('summary.attemptCount', 1)
            ->assertJsonPath('summary.totalXp', 280)
            ->assertJsonCount(1, 'sessions');
    }

    public function test_user_can_toggle_session_bookmark(): void
    {
        $user = $this->createVolunteerUser();
        $session = SimulationSession::create([
            'user_id' => $user->id,
            'scenario_id' => 'booth-suffering',
            'scenario_title' => 'Mercy and the Problem of Pain',
            'overall_score' => 200,
            'atmosphere_score' => 80,
            'completed_at' => now(),
        ]);

        $this->actingAs($user)
            ->patchJson("/api/v1/simulations/sessions/{$session->id}/bookmark", [
                'is_bookmarked' => true,
            ])
            ->assertOk()
            ->assertJsonPath('session.isBookmarked', true);

        $this->assertDatabaseHas('simulation_sessions', [
            'id' => $session->id,
            'is_bookmarked' => true,
        ]);
    }

    public function test_user_cannot_bookmark_another_users_session(): void
    {
        $owner = $this->createVolunteerUser();
        $intruder = $this->createVolunteerUser();

        $session = SimulationSession::create([
            'user_id' => $owner->id,
            'scenario_id' => 'booth-suffering',
            'scenario_title' => 'Mercy and the Problem of Pain',
            'overall_score' => 200,
            'atmosphere_score' => 80,
            'completed_at' => now(),
        ]);

        $this->actingAs($intruder)
            ->patchJson("/api/v1/simulations/sessions/{$session->id}/bookmark", [
                'is_bookmarked' => true,
            ])
            ->assertForbidden();
    }
}
