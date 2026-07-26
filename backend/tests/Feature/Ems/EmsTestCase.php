<?php

namespace Tests\Feature\Ems;

use App\Ems\Models\Event;
use App\Ems\Models\EventCategory;
use App\Ems\Support\EmsRoles;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\Ems\EmsRolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Shared setup for EMS feature tests.
 *
 * Seeds the real EMS roles and permissions rather than hand-rolling fixtures,
 * so the tests exercise the same access-control data a deployment gets.
 */
abstract class EmsTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // super-admin belongs to the platform, so the EMS seeder grants to it
        // rather than creating it. Tests need it to exist first.
        Role::firstOrCreate(
            ['slug' => EmsRoles::SUPER_ADMIN],
            ['name' => 'Super Admin', 'uuid' => (string) Str::uuid()]
        );

        $this->seed(EmsRolePermissionSeeder::class);
    }

    /**
     * Create a user holding the given EMS role.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function emsUser(?string $roleSlug = null, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);

        if ($roleSlug !== null) {
            $role = Role::where('slug', $roleSlug)->firstOrFail();
            $user->roles()->syncWithoutDetaching([$role->id]);
        }

        return $user->fresh();
    }

    /**
     * Authenticate subsequent requests as the given user using a real Sanctum
     * bearer token, which is how the EMS frontend talks to the API.
     */
    protected function actingAsEms(User $user): static
    {
        $token = $user->createToken('ems-test')->plainTextToken;

        return $this->forgetAuthentication()->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ]);
    }

    /**
     * Drop the resolved auth guard.
     *
     * The application container survives between requests inside a single
     * test, so Sanctum would otherwise keep serving the user it resolved the
     * first time. A real client is resolved afresh on every request.
     */
    protected function forgetAuthentication(): static
    {
        $this->app['auth']->forgetGuards();

        return $this;
    }

    protected function category(array $attributes = []): EventCategory
    {
        return EventCategory::factory()->create($attributes);
    }

    protected function event(array $attributes = []): Event
    {
        return Event::factory()->create($attributes);
    }

    /**
     * The base path for the EMS API, read from config so a deployment that
     * remounts the module does not break the suite.
     */
    protected function url(string $path = ''): string
    {
        return '/' . trim((string) config('ems.route.prefix', 'api/v1/ems'), '/')
            . ($path === '' ? '' : '/' . ltrim($path, '/'));
    }

    /**
     * Assert the standard EMS success envelope.
     */
    protected function assertSuccessEnvelope(TestResponse $response): void
    {
        $response->assertJsonStructure(['success', 'message', 'data', 'meta']);
        $response->assertJsonPath('success', true);
    }

    /**
     * Assert the standard EMS error envelope.
     */
    protected function assertErrorEnvelope(TestResponse $response): void
    {
        $response->assertJsonStructure(['success', 'message']);
        $response->assertJsonPath('success', false);
    }
}
