<?php

namespace Tests\Feature\Phase9;

use App\Ems\Support\EmsPermissions;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Phase 9 — Legacy CMS event ownership retirement.
 */
class LegacyCmsEventsRetirementTest extends TestCase
{
    use RefreshDatabase;

    private function perm(string $slug, string $module = 'Website'): Permission
    {
        return Permission::create([
            'uuid' => (string) Str::uuid(),
            'name' => $slug,
            'slug' => $slug,
            'module' => $module,
            'description' => $slug,
        ]);
    }

    private function role(string $slug, string $name): Role
    {
        return Role::create([
            'uuid' => (string) Str::uuid(),
            'name' => $name,
            'slug' => $slug,
            'description' => $name,
        ]);
    }

    private function user(string $email, Role $role): User
    {
        $user = User::factory()->create(['email' => $email]);
        $user->roles()->attach($role);

        return $user;
    }

    /** @test */
    public function legacy_website_events_api_returns_410_gone(): void
    {
        $this->getJson('/api/v1/website/events')
            ->assertStatus(410)
            ->assertJsonPath('retired', true)
            ->assertJsonPath('replacement', '/api/v1/ems/public/events');

        $this->getJson('/api/v1/website/events/some-uuid')
            ->assertStatus(410);

        $this->postJson('/api/v1/website/events/some-uuid/rsvp', [
            'attendees' => [
                ['firstName' => 'A', 'lastName' => 'B', 'email' => 'a@example.com', 'phone' => ''],
            ],
        ])->assertStatus(410);

        $this->deleteJson('/api/v1/website/events/some-uuid/rsvp')
            ->assertStatus(410);

        $this->getJson('/api/v1/website/events/registrations')
            ->assertStatus(410);
    }

    /** @test */
    public function main_website_consumes_ems_public_events_not_legacy_cms(): void
    {
        $this->getJson('/api/v1/ems/public/events')
            ->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    /** @test */
    public function admin_cms_events_routes_are_not_registered(): void
    {
        $this->getJson('/api/v1/admin/cms/events')->assertStatus(404);
        $this->postJson('/api/v1/admin/cms/events', [])->assertStatus(404);
    }

    /** @test */
    public function manage_events_is_not_an_ems_permission(): void
    {
        $legacy = $this->perm('manage_events', 'Website');
        $role = $this->role('legacy-cms-events', 'Legacy CMS Events');
        $role->permissions()->attach($legacy);
        $user = $this->user('legacy-cms-events@example.com', $role);

        $this->assertFalse($user->hasPermission(EmsPermissions::EVENTS_VIEW));
        $this->assertFalse($user->hasPermission(EmsPermissions::EVENTS_CREATE));
        $this->assertFalse($user->hasPermission(EmsPermissions::EVENTS_UPDATE));

        $token = $user->createToken('phase9')->plainTextToken;
        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->getJson('/api/v1/ems/events')->assertStatus(403);
    }

    /** @test */
    public function ems_events_permission_does_not_grant_cms_administration(): void
    {
        $emsView = $this->perm(EmsPermissions::EVENTS_VIEW, 'EMS');
        $role = $this->role('ems-only', 'EMS Only');
        $role->permissions()->attach($emsView);
        $user = $this->user('ems-only@example.com', $role);

        $this->assertFalse($user->hasPermission('manage_homepage'));
        $this->assertFalse($user->hasPermission('manage_announcements'));
        $this->assertFalse($user->hasPermission('manage_events'));

        $this->actingAs($user)
            ->getJson(route('api.admin.cms.homepage.index'))
            ->assertStatus(403);
    }

    /** @test */
    public function legacy_cms_event_tables_are_retained_for_archive(): void
    {
        $this->assertTrue(Schema::hasTable('events'));
        $this->assertTrue(Schema::hasTable('event_registrations'));
        $this->assertFalse(config('cms.legacy_events.drop_schema'));
        $this->assertSame('archived', config('cms.legacy_events.status'));
        $this->assertSame('retired_410', config('cms.legacy_events.api'));
    }

    /** @test */
    public function ems_tables_do_not_foreign_key_to_legacy_cms_events(): void
    {
        $this->assertTrue(Schema::hasTable('ems_events'));

        // ems_events must not reference legacy `events.id`
        $foreignKeys = collect(Schema::getForeignKeys('ems_events'))
            ->pluck('foreign_table')
            ->all();

        $this->assertNotContains('events', $foreignKeys);
        $this->assertNotContains('event_registrations', $foreignKeys);
    }
}
