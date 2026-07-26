<?php

namespace Tests\Feature\Ems;

use App\Ems\Support\EmsPermissions;
use App\Ems\Support\EmsRoles;
use Illuminate\Support\Facades\Hash;

/**
 * The EMS has no authentication of its own: it consumes the platform's
 * existing Sanctum endpoints. These tests confirm that integration works
 * end to end and that the EMS API rejects anything unauthenticated.
 */
class EmsAuthenticationTest extends EmsTestCase
{
    public function test_platform_login_issues_a_token_that_the_ems_api_accepts(): void
    {
        $user = $this->emsUser(EmsRoles::EVENT_ADMINISTRATOR, [
            'email' => 'organizer@sfu.ca',
            'password' => Hash::make('correct-horse-battery'),
        ]);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'organizer@sfu.ca',
            'password' => 'correct-horse-battery',
        ]);

        $login->assertOk()->assertJsonStructure(['message', 'user', 'token']);

        $token = $login->json('token');

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson($this->url('users/me'))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.uuid', $user->uuid);
    }

    public function test_current_user_endpoint_returns_only_ems_permissions(): void
    {
        $user = $this->emsUser(EmsRoles::EVENT_ORGANIZER);

        $response = $this->actingAsEms($user)->getJson($this->url('users/me'));

        $response->assertOk();
        $this->assertSuccessEnvelope($response);

        $permissions = $response->json('data.permissions');

        $this->assertContains(EmsPermissions::EVENTS_CREATE, $permissions);

        // Every returned permission belongs to the EMS namespace: the EMS
        // client is never handed the user's academy or CMS capabilities.
        $this->assertSame([], array_diff($permissions, EmsPermissions::all()));

        $response->assertJsonPath('data.has_ems_access', true);
    }

    public function test_user_without_any_ems_permission_is_flagged_as_having_no_access(): void
    {
        $user = $this->emsUser(EmsRoles::ATTENDEE);

        $this->actingAsEms($user)
            ->getJson($this->url('users/me'))
            ->assertOk()
            ->assertJsonPath('data.has_ems_access', false)
            ->assertJsonPath('data.permissions', []);
    }

    public function test_unauthenticated_requests_are_rejected_with_the_ems_error_envelope(): void
    {
        $response = $this->getJson($this->url('events'));

        $response->assertUnauthorized();
        $this->assertErrorEnvelope($response);
        $response->assertJsonPath('message', 'Unauthenticated. Sign in to continue.');
    }

    public function test_every_ems_endpoint_requires_authentication(): void
    {
        $endpoints = [
            ['get', 'dashboard'],
            ['get', 'events'],
            ['post', 'events'],
            ['get', 'event-categories'],
            ['post', 'event-categories'],
            ['get', 'roles'],
            ['get', 'permissions'],
            ['get', 'users/me'],
        ];

        foreach ($endpoints as [$method, $path]) {
            $this->{$method . 'Json'}($this->url($path))
                ->assertUnauthorized();
        }
    }

    public function test_a_revoked_token_stops_working(): void
    {
        $user = $this->emsUser(EmsRoles::EVENT_ADMINISTRATOR);
        $token = $user->createToken('ems-test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson($this->url('users/me'))
            ->assertOk();

        // Simulate logout: the platform's AuthService deletes the user's tokens.
        $user->tokens()->delete();

        $this->forgetAuthentication()
            ->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson($this->url('users/me'))
            ->assertUnauthorized();
    }

    public function test_platform_logout_invalidates_ems_access(): void
    {
        $user = $this->emsUser(EmsRoles::EVENT_ADMINISTRATOR, [
            'email' => 'staffer@sfu.ca',
            'password' => Hash::make('correct-horse-battery'),
        ]);

        $token = $this->postJson('/api/v1/auth/login', [
            'email' => 'staffer@sfu.ca',
            'password' => 'correct-horse-battery',
        ])->json('token');

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        $this->forgetAuthentication()
            ->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson($this->url('users/me'))
            ->assertUnauthorized();
    }

    public function test_an_unknown_ems_endpoint_returns_a_structured_not_found(): void
    {
        $user = $this->emsUser(EmsRoles::EVENT_ADMINISTRATOR);

        $response = $this->actingAsEms($user)->getJson($this->url('does-not-exist'));

        $response->assertNotFound();
        $this->assertErrorEnvelope($response);
    }
}
