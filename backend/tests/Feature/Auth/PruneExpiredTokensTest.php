<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/**
 * Exercises the installed sanctum:prune-expired against a disposable database.
 * It is not scheduled; see PHASE_24 docs for scheduling requirements.
 */
class PruneExpiredTokensTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-14 12:00:00'));
        $this->user = User::factory()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function token(string $name, Carbon $createdAt, ?Carbon $expiresAt = null): void
    {
        $token = $this->user->createToken($name, ['*'], $expiresAt)->accessToken;
        $token->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();
    }

    /**
     * @return list<string>
     */
    private function remaining(): array
    {
        return PersonalAccessToken::query()->orderBy('name')->pluck('name')->all();
    }

    private function seedMatrix(): void
    {
        $now = now();
        $this->token('a_valid_recent', $now->copy()->subDay());
        $this->token('b_config_expired_within_retention', $now->copy()->subMinutes(10080 + 60));
        $this->token('c_config_expired_beyond_retention', $now->copy()->subMinutes(10080 + 25 * 60));
        $this->token('d_expires_at_past_beyond_retention', $now->copy()->subDays(3), $now->copy()->subDays(2));
        $this->token('e_expires_at_past_within_retention', $now->copy()->subDays(1), $now->copy()->subHour());
        $this->token('f_expires_at_future_recent', $now->copy()->subHour(), $now->copy()->addDays(2));
        $this->token('g_expires_at_future_but_created_9_days_ago', $now->copy()->subDays(9), $now->copy()->addDays(30));
    }

    public function test_command_is_registered_with_hours_option(): void
    {
        $definition = Artisan::all()['sanctum:prune-expired']->getDefinition();

        $this->assertTrue($definition->hasOption('hours'));
        $this->assertSame('24', (string) $definition->getOption('hours')->getDefault());
    }

    public function test_default_retention_prunes_only_tokens_expired_more_than_24_hours(): void
    {
        $this->seedMatrix();

        $this->artisan('sanctum:prune-expired', ['--hours' => 24])->assertExitCode(0);

        $this->assertSame([
            'a_valid_recent',
            'b_config_expired_within_retention',
            'e_expires_at_past_within_retention',
            'f_expires_at_future_recent',
        ], $this->remaining());
        $this->assertNotNull($this->user->fresh());
    }

    public function test_zero_retention_prunes_every_expired_token_but_keeps_valid_ones(): void
    {
        $this->seedMatrix();

        $this->artisan('sanctum:prune-expired', ['--hours' => 0])->assertExitCode(0);

        $this->assertSame(['a_valid_recent', 'f_expires_at_future_recent'], $this->remaining());
    }

    public function test_retained_expired_tokens_are_still_rejected_by_the_guard(): void
    {
        $plain = $this->user->createToken('retained')->plainTextToken;
        PersonalAccessToken::query()->update(['created_at' => now()->subMinutes(10080 + 60)]);

        $this->artisan('sanctum:prune-expired', ['--hours' => 24])->assertExitCode(0);
        $this->assertSame(['retained'], $this->remaining());

        $this->withHeaders(['Authorization' => 'Bearer '.$plain])->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_other_users_and_tables_are_untouched(): void
    {
        $other = User::factory()->create();
        $other->createToken('other_valid');
        $this->seedMatrix();

        $this->artisan('sanctum:prune-expired', ['--hours' => 24])->assertExitCode(0);

        $this->assertSame(2, User::count());
        $this->assertSame(1, $other->tokens()->count());
    }

    public function test_negative_hours_would_delete_valid_tokens(): void
    {
        $this->seedMatrix();

        $this->artisan('sanctum:prune-expired', ['--hours' => -168])->assertExitCode(0);

        $this->assertSame([], $this->remaining(), 'Documents why the schedule must pin a non-negative --hours value.');
    }
}
