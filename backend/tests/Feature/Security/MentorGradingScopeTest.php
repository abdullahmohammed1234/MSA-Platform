<?php

namespace Tests\Feature\Security;

use App\Models\MentorAssignment;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MentorGradingScopeTest extends TestCase
{
    use RefreshDatabase;

    private function withRole(string $slug): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['slug' => $slug], ['name' => ucfirst($slug)]));

        return $user->fresh();
    }

    private function attemptFor(User $student, int $score = 40): QuizAttempt
    {
        return QuizAttempt::create([
            'user_id' => $student->id,
            'quiz_id' => Quiz::factory()->create(['passing_score' => 70])->id,
            'score' => $score,
            'passed' => false,
            'started_at' => now()->subHour(),
            'submitted_at' => now(),
        ]);
    }

    public function test_mentor_cannot_grade_an_unassigned_students_attempt(): void
    {
        $mentor = $this->withRole('mentor');
        $attempt = $this->attemptFor(User::factory()->create());
        Sanctum::actingAs($mentor);

        $this->postJson("/api/v1/mentor/submissions/{$attempt->id}/grade", ['score' => 100, 'passed' => true])
            ->assertForbidden();

        $this->assertSame(40, $attempt->fresh()->score);
        $this->assertFalse((bool) $attempt->fresh()->passed);
    }

    public function test_mentor_can_grade_an_assigned_students_attempt(): void
    {
        $mentor = $this->withRole('mentor');
        $student = User::factory()->create();
        MentorAssignment::create(['mentor_id' => $mentor->id, 'student_id' => $student->id, 'status' => 'active']);
        $attempt = $this->attemptFor($student);
        Sanctum::actingAs($mentor);

        $this->postJson("/api/v1/mentor/submissions/{$attempt->id}/grade", ['score' => 85])->assertOk();

        $this->assertSame(85, $attempt->fresh()->score);
        $this->assertTrue((bool) $attempt->fresh()->passed);
    }

    public function test_inactive_assignment_does_not_allow_grading(): void
    {
        $mentor = $this->withRole('mentor');
        $student = User::factory()->create();
        MentorAssignment::create(['mentor_id' => $mentor->id, 'student_id' => $student->id, 'status' => 'inactive']);
        $attempt = $this->attemptFor($student);
        Sanctum::actingAs($mentor);

        $this->postJson("/api/v1/mentor/submissions/{$attempt->id}/grade", ['score' => 100])->assertForbidden();
    }

    public function test_admin_can_grade_any_attempt(): void
    {
        $attempt = $this->attemptFor(User::factory()->create());
        Sanctum::actingAs($this->withRole('admin'));

        $this->postJson("/api/v1/mentor/submissions/{$attempt->id}/grade", ['score' => 90])->assertOk();
    }

    public function test_mentor_without_students_sees_no_other_users_submissions(): void
    {
        $mentor = $this->withRole('mentor');
        $outsider = User::factory()->create(['name' => 'Outsider Student']);
        $this->attemptFor($outsider);
        Sanctum::actingAs($mentor);

        $response = $this->getJson('/api/v1/mentor/dashboard')->assertOk();

        $this->assertSame([], $response->json('submissions'));
        $this->assertSame([], $response->json('quizAnalytics'));
        $this->assertStringNotContainsString('Outsider Student', $response->getContent());
    }

    public function test_mentor_dashboard_shows_only_assigned_students_submissions(): void
    {
        $mentor = $this->withRole('mentor');
        $student = User::factory()->create(['name' => 'Assigned Student']);
        MentorAssignment::create(['mentor_id' => $mentor->id, 'student_id' => $student->id, 'status' => 'active']);
        $this->attemptFor($student);
        $this->attemptFor(User::factory()->create(['name' => 'Outsider Student']));
        Sanctum::actingAs($mentor);

        $response = $this->getJson('/api/v1/mentor/dashboard')->assertOk();

        $this->assertCount(1, $response->json('submissions'));
        $this->assertStringContainsString('Assigned Student', $response->getContent());
        $this->assertStringNotContainsString('Outsider Student', $response->getContent());
    }
}
