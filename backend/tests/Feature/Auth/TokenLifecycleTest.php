<?php

namespace Tests\Feature\Auth;

use App\Http\Middleware\Authenticate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Phase 23 authentication policy: 7-day token lifetime, revoke-all on
 * password change with one replacement, and inactive-account rejection on
 * every authenticated request. Assertions use status codes and row counts
 * only; plaintext tokens are never printed.
 */
class TokenLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private const LIFETIME_MINUTES = 10080;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-13 12:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function makeUser(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'email' => 'member'.Str::lower(Str::random(6)).'@sfu.ca',
            'password' => Hash::make('OldPassword123!'),
            'is_active' => true,
            'email_verified_at' => now(),
        ], $attributes));
    }

    private function bearer(string $token, string $method, string $uri, array $data = []): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->json($method, $uri, $data);
    }

    private function changePassword(string $token): TestResponse
    {
        return $this->bearer($token, 'PUT', '/api/v1/account/password', [
            'current_password' => 'OldPassword123!',
            'new_password' => 'NewSecretPassword123!',
            'new_password_confirmation' => 'NewSecretPassword123!',
        ]);
    }

    public function test_sanctum_expiration_is_seven_days(): void
    {
        $this->assertSame(self::LIFETIME_MINUTES, config('sanctum.expiration'));
    }

    public function test_token_is_accepted_just_before_seven_days(): void
    {
        $token = $this->makeUser()->createToken('auth_token')->plainTextToken;

        Carbon::setTestNow(now()->addMinutes(self::LIFETIME_MINUTES - 1));

        $this->bearer($token, 'GET', '/api/v1/auth/me')->assertOk();
    }

    public function test_token_is_rejected_at_exactly_seven_days(): void
    {
        $token = $this->makeUser()->createToken('auth_token')->plainTextToken;

        Carbon::setTestNow(now()->addMinutes(self::LIFETIME_MINUTES));

        $this->bearer($token, 'GET', '/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_token_is_rejected_after_seven_days(): void
    {
        $token = $this->makeUser()->createToken('auth_token')->plainTextToken;

        Carbon::setTestNow(now()->addMinutes(self::LIFETIME_MINUTES + 1));

        $this->bearer($token, 'GET', '/api/v1/auth/me')->assertUnauthorized();
        $this->bearer($token, 'GET', '/api/v1/account/summary')->assertUnauthorized();
    }

    public function test_login_issues_a_token_that_expires_after_seven_days(): void
    {
        $user = $this->makeUser();

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'OldPassword123!',
        ])->assertOk();

        $token = $login->json('data.token') ?? $login->json('token');
        $this->assertIsString($token);
        $this->assertSame(1, $user->tokens()->count());

        Carbon::setTestNow(now()->addMinutes(self::LIFETIME_MINUTES + 1));

        $this->bearer($token, 'GET', '/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_password_change_revokes_all_tokens_and_issues_exactly_one(): void
    {
        $user = $this->makeUser();
        $current = $user->createToken('auth_token')->plainTextToken;
        $otherDevice = $user->createToken('auth_token')->plainTextToken;
        $thirdDevice = $user->createToken('auth_token')->plainTextToken;
        $this->assertSame(3, $user->tokens()->count());

        $response = $this->changePassword($current)->assertOk()
            ->assertJsonPath('message', 'Password updated successfully.');

        $replacement = $response->json('token');
        $this->assertIsString($replacement);
        $this->assertSame(1, $user->tokens()->count());

        $this->bearer($current, 'GET', '/api/v1/auth/me')->assertUnauthorized();
        $this->bearer($otherDevice, 'GET', '/api/v1/auth/me')->assertUnauthorized();
        $this->bearer($thirdDevice, 'GET', '/api/v1/auth/me')->assertUnauthorized();
        $this->bearer($replacement, 'GET', '/api/v1/auth/me')->assertOk();
    }

    public function test_password_change_does_not_touch_other_users_tokens(): void
    {
        $user = $this->makeUser();
        $other = $this->makeUser();
        $token = $user->createToken('auth_token')->plainTextToken;
        $otherToken = $other->createToken('auth_token')->plainTextToken;

        $this->changePassword($token)->assertOk();

        $this->assertSame(1, $other->tokens()->count());
        $this->bearer($otherToken, 'GET', '/api/v1/auth/me')->assertOk();
    }

    public function test_failed_password_change_keeps_all_tokens(): void
    {
        $user = $this->makeUser();
        $current = $user->createToken('auth_token')->plainTextToken;
        $user->createToken('auth_token');

        $this->bearer($current, 'PUT', '/api/v1/account/password', [
            'current_password' => 'WrongPassword!',
            'new_password' => 'NewSecretPassword123!',
            'new_password_confirmation' => 'NewSecretPassword123!',
        ])->assertStatus(422);

        $this->assertSame(2, $user->tokens()->count());
        $this->assertTrue(Hash::check('OldPassword123!', $user->fresh()->password));
        $this->bearer($current, 'GET', '/api/v1/auth/me')->assertOk();
    }

    public function test_replacement_token_also_expires_after_seven_days(): void
    {
        $user = $this->makeUser();
        $current = $user->createToken('auth_token')->plainTextToken;

        Carbon::setTestNow(now()->addDays(3));
        $replacement = $this->changePassword($current)->assertOk()->json('token');

        Carbon::setTestNow(now()->addMinutes(self::LIFETIME_MINUTES - 1));
        $this->bearer($replacement, 'GET', '/api/v1/auth/me')->assertOk();

        Carbon::setTestNow(now()->addMinutes(2));
        $this->bearer($replacement, 'GET', '/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_inactive_user_token_is_rejected_on_authenticated_routes(): void
    {
        $user = $this->makeUser();
        $token = $user->createToken('auth_token')->plainTextToken;

        $user->forceFill(['is_active' => false])->save();

        foreach (['/api/v1/auth/me', '/api/v1/users/me', '/api/v1/account/summary', '/api/v1/notifications'] as $uri) {
            $response = $this->bearer($token, 'GET', $uri);
            $response->assertUnauthorized()
                ->assertJsonPath('message', Authenticate::DEACTIVATED_MESSAGE);
            $this->assertStringNotContainsString($token, $response->getContent());
        }
    }

    public function test_inactive_user_cannot_change_password_or_logout_with_old_token(): void
    {
        $user = $this->makeUser();
        $token = $user->createToken('auth_token')->plainTextToken;
        $user->forceFill(['is_active' => false])->save();

        $this->changePassword($token)->assertUnauthorized();
        $this->bearer($token, 'POST', '/api/v1/auth/logout')->assertUnauthorized();

        $this->assertTrue(Hash::check('OldPassword123!', $user->fresh()->password));
    }

    public function test_inactive_user_is_rejected_when_authenticated_via_session_guard(): void
    {
        $user = $this->makeUser(['is_active' => false]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertJsonPath('message', Authenticate::DEACTIVATED_MESSAGE);
    }

    public function test_inactive_user_cannot_log_in(): void
    {
        $user = $this->makeUser(['is_active' => false]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'OldPassword123!',
        ]);

        $this->assertContains($response->status(), [401, 403, 422]);
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_reactivated_user_unexpired_token_works_again(): void
    {
        $user = $this->makeUser();
        $token = $user->createToken('auth_token')->plainTextToken;

        $user->forceFill(['is_active' => false])->save();
        $this->bearer($token, 'GET', '/api/v1/auth/me')->assertUnauthorized();

        $user->forceFill(['is_active' => true])->save();
        $this->bearer($token, 'GET', '/api/v1/auth/me')->assertOk();
    }

    public function test_active_user_is_unaffected_by_inactive_check(): void
    {
        $token = $this->makeUser()->createToken('auth_token')->plainTextToken;

        $this->bearer($token, 'GET', '/api/v1/account/summary')->assertOk();
    }

    public function test_unauthenticated_request_still_returns_standard_401(): void
    {
        $this->getJson('/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_logout_revokes_only_the_current_token(): void
    {
        $user = $this->makeUser();
        $current = $user->createToken('auth_token')->plainTextToken;
        $otherDevice = $user->createToken('auth_token')->plainTextToken;

        $this->bearer($current, 'POST', '/api/v1/auth/logout')->assertOk();

        $this->assertSame(1, $user->tokens()->count());
        $this->bearer($current, 'GET', '/api/v1/auth/me')->assertUnauthorized();
        $this->bearer($otherDevice, 'GET', '/api/v1/auth/me')->assertOk();
    }

    public function test_password_reset_still_revokes_all_tokens(): void
    {
        $user = $this->makeUser();
        $first = $user->createToken('auth_token')->plainTextToken;
        $user->createToken('auth_token');

        $resetToken = Str::random(64);
        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'token' => Hash::make($resetToken),
            'created_at' => now(),
        ]);

        $this->postJson(route('api.auth.reset-password'), [
            'email' => $user->email,
            'token' => $resetToken,
            'password' => 'NewSecurePassword123!',
            'password_confirmation' => 'NewSecurePassword123!',
        ])->assertOk();

        $this->assertSame(0, $user->tokens()->count());
        $this->bearer($first, 'GET', '/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_token_level_expires_at_sooner_than_seven_days_is_enforced(): void
    {
        $token = $this->makeUser()->createToken('auth_token', ['*'], now()->addDay())->plainTextToken;

        Carbon::setTestNow(now()->addDay()->subMinute());
        $this->bearer($token, 'GET', '/api/v1/auth/me')->assertOk();

        Carbon::setTestNow(now()->addMinutes(2));
        $this->bearer($token, 'GET', '/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_token_level_expires_at_cannot_extend_past_seven_days(): void
    {
        $token = $this->makeUser()->createToken('auth_token', ['*'], now()->addDays(30))->plainTextToken;

        Carbon::setTestNow(now()->addMinutes(self::LIFETIME_MINUTES + 1));

        $this->bearer($token, 'GET', '/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_inactive_super_admin_gets_401_before_role_and_app_access_checks(): void
    {
        $admin = $this->makeUser();
        $admin->roles()->attach(\App\Models\Role::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Admin']));
        $token = $admin->createToken('auth_token')->plainTextToken;

        $this->assertNotSame(401, $this->bearer($token, 'GET', '/api/v1/store-admin/products')->status());

        $admin->forceFill(['is_active' => false])->save();

        foreach (['/api/v1/admin/dashboard', '/api/v1/store-admin/products', '/api/v1/ems/public/my-tickets'] as $uri) {
            $this->bearer($token, 'GET', $uri)
                ->assertUnauthorized()
                ->assertJsonPath('message', Authenticate::DEACTIVATED_MESSAGE);
        }
    }

    public function test_password_change_is_atomic_when_token_issuance_fails(): void
    {
        $user = $this->makeUser();
        $current = $user->createToken('auth_token')->plainTextToken;
        $user->createToken('auth_token');

        \Laravel\Sanctum\PersonalAccessToken::creating(function () {
            throw new \RuntimeException('simulated token store failure');
        });

        $this->withoutExceptionHandling();
        try {
            $this->changePassword($current);
            $this->fail('Expected the simulated failure to propagate.');
        } catch (\RuntimeException $e) {
            $this->assertSame('simulated token store failure', $e->getMessage());
        } finally {
            \Laravel\Sanctum\PersonalAccessToken::flushEventListeners();
        }

        $this->assertTrue(Hash::check('OldPassword123!', $user->fresh()->password));
        $this->assertSame(2, $user->tokens()->count());
    }

    public function test_issued_tokens_keep_wildcard_abilities(): void
    {
        $user = $this->makeUser();
        $token = $user->createToken('auth_token')->plainTextToken;

        $replacement = $this->changePassword($token)->assertOk()->json('token');
        $this->assertIsString($replacement);

        $this->assertSame(['*'], $user->tokens()->firstOrFail()->abilities);
        $this->assertNull($user->tokens()->firstOrFail()->expires_at);
    }
}
