<?php

namespace Tests\Feature\Ems;

use App\Ems\Models\Event;
use App\Ems\Models\EventStaff;
use App\Ems\Support\EmsPermissions;
use App\Ems\Support\EmsRoles;
use App\Models\Permission;
use App\Models\Role;

/**
 * RBAC enforcement.
 *
 * The point of these tests is that capability comes from granular permissions
 * and record scope, never from a role name — so granting a bare permission is
 * enough, and holding a role with the wrong permissions is not.
 */
class EmsAuthorizationTest extends EmsTestCase
{
    public function test_the_five_initial_roles_exist_with_their_permission_grants(): void
    {
        foreach ([
            EmsRoles::SUPER_ADMIN,
            EmsRoles::EVENT_ADMINISTRATOR,
            EmsRoles::EVENT_ORGANIZER,
            EmsRoles::EVENT_STAFF,
            EmsRoles::ATTENDEE,
        ] as $slug) {
            $this->assertDatabaseHas('roles', ['slug' => $slug]);
        }

        foreach (EmsPermissions::all() as $slug) {
            $this->assertDatabaseHas('permissions', [
                'slug' => $slug,
                'module' => EmsPermissions::MODULE,
            ]);
        }
    }

    public function test_super_admin_holds_every_ems_permission(): void
    {
        $user = $this->emsUser(EmsRoles::SUPER_ADMIN);

        foreach (EmsPermissions::all() as $permission) {
            $this->assertTrue(
                $user->hasPermission($permission),
                "Super Admin should hold {$permission}."
            );
        }
    }

    public function test_event_administrator_does_not_get_system_administration(): void
    {
        $user = $this->emsUser(EmsRoles::EVENT_ADMINISTRATOR);

        $this->assertTrue($user->hasPermission(EmsPermissions::EVENTS_ARCHIVE));
        $this->assertTrue($user->hasPermission(EmsPermissions::CATEGORIES_DELETE));
        $this->assertTrue($user->hasPermission(EmsPermissions::SYSTEM_VIEW));

        $this->assertFalse(
            $user->hasPermission(EmsPermissions::SYSTEM_MANAGE),
            'Event Administrator must not hold system-level administration.'
        );
    }

    public function test_event_organizer_cannot_delete_events_or_manage_categories(): void
    {
        $user = $this->emsUser(EmsRoles::EVENT_ORGANIZER);

        $this->assertTrue($user->hasPermission(EmsPermissions::EVENTS_CREATE));
        $this->assertTrue($user->hasPermission(EmsPermissions::EVENTS_PUBLISH));

        $this->assertFalse($user->hasPermission(EmsPermissions::EVENTS_DELETE));
        $this->assertFalse($user->hasPermission(EmsPermissions::EVENTS_ARCHIVE));
        $this->assertFalse($user->hasPermission(EmsPermissions::CATEGORIES_CREATE));
        $this->assertFalse($user->hasPermission(EmsPermissions::EVENTS_VIEW_ALL));
    }

    public function test_event_staff_is_read_only(): void
    {
        $user = $this->emsUser(EmsRoles::EVENT_STAFF);

        $this->assertTrue($user->hasPermission(EmsPermissions::EVENTS_VIEW));
        $this->assertFalse($user->hasPermission(EmsPermissions::EVENTS_CREATE));
        $this->assertFalse($user->hasPermission(EmsPermissions::EVENTS_UPDATE));
        $this->assertFalse($user->hasPermission(EmsPermissions::EVENTS_PUBLISH));
    }

    // -----------------------------------------------------------------
    // Endpoint-level enforcement
    // -----------------------------------------------------------------

    public function test_attendee_is_refused_the_event_list(): void
    {
        $response = $this->actingAsEms($this->emsUser(EmsRoles::ATTENDEE))
            ->getJson($this->url('events'));

        $response->assertForbidden();
        $this->assertErrorEnvelope($response);
    }

    public function test_staff_cannot_create_an_event(): void
    {
        $this->actingAsEms($this->emsUser(EmsRoles::EVENT_STAFF))
            ->postJson($this->url('events'), [
                'name' => 'Unauthorized Event',
                'start_at' => now()->addWeek()->toDateTimeString(),
            ])
            ->assertForbidden();
    }

    public function test_organizer_cannot_delete_their_own_event(): void
    {
        $organizer = $this->emsUser(EmsRoles::EVENT_ORGANIZER);
        $event = Event::factory()->organizedBy($organizer)->create();

        $this->actingAsEms($organizer)
            ->deleteJson($this->url("events/{$event->uuid}"))
            ->assertForbidden();

        $this->assertDatabaseHas('ems_events', ['id' => $event->id, 'deleted_at' => null]);
    }

    public function test_a_permission_granted_directly_is_enough_without_any_role(): void
    {
        // No role at all — just the two permissions, granted straight to the
        // user. This is the check that proves authorization is not keyed on
        // role names anywhere in the stack.
        $user = $this->emsUser();

        $user->permissions()->sync(
            Permission::whereIn('slug', [EmsPermissions::EVENTS_VIEW, EmsPermissions::EVENTS_CREATE])
                ->pluck('id')
                ->all()
        );

        $this->actingAsEms($user->fresh())
            ->postJson($this->url('events'), [
                'name' => 'Granted By Permission',
                'start_at' => now()->addWeek()->toDateTimeString(),
            ])
            ->assertCreated();
    }

    public function test_holding_a_role_without_the_permission_is_not_enough(): void
    {
        // Strip publish from the organizer role and confirm the endpoint
        // follows the permission, not the role.
        $role = Role::where('slug', EmsRoles::EVENT_ORGANIZER)->firstOrFail();
        $publish = Permission::where('slug', EmsPermissions::EVENTS_PUBLISH)->firstOrFail();
        $role->permissions()->detach($publish->id);

        $organizer = $this->emsUser(EmsRoles::EVENT_ORGANIZER);
        $event = Event::factory()->organizedBy($organizer)->create();

        $this->actingAsEms($organizer)
            ->postJson($this->url("events/{$event->uuid}/transitions"), ['action' => 'publish'])
            ->assertForbidden();
    }

    // -----------------------------------------------------------------
    // Record scoping
    // -----------------------------------------------------------------

    public function test_organizer_only_sees_their_own_events_in_the_list(): void
    {
        $organizer = $this->emsUser(EmsRoles::EVENT_ORGANIZER);
        $other = $this->emsUser(EmsRoles::EVENT_ORGANIZER);

        $mine = Event::factory()->organizedBy($organizer)->create();
        $theirs = Event::factory()->organizedBy($other)->create();

        $response = $this->actingAsEms($organizer)->getJson($this->url('events'));

        $response->assertOk();

        $uuids = array_column($response->json('data'), 'uuid');

        $this->assertContains($mine->uuid, $uuids);
        $this->assertNotContains($theirs->uuid, $uuids);
    }

    public function test_organizer_cannot_read_or_edit_an_event_they_are_not_on(): void
    {
        $organizer = $this->emsUser(EmsRoles::EVENT_ORGANIZER);
        $other = $this->emsUser(EmsRoles::EVENT_ORGANIZER);
        $theirs = Event::factory()->organizedBy($other)->create();

        $this->actingAsEms($organizer)
            ->getJson($this->url("events/{$theirs->uuid}"))
            ->assertForbidden();

        $this->actingAsEms($organizer)
            ->putJson($this->url("events/{$theirs->uuid}"), ['name' => 'Hijacked Event'])
            ->assertForbidden();

        $this->assertDatabaseMissing('ems_events', ['name' => 'Hijacked Event']);
    }

    public function test_event_administrator_sees_every_event(): void
    {
        $administrator = $this->emsUser(EmsRoles::EVENT_ADMINISTRATOR);
        $organizer = $this->emsUser(EmsRoles::EVENT_ORGANIZER);

        $theirs = Event::factory()->organizedBy($organizer)->create();

        $response = $this->actingAsEms($administrator)->getJson($this->url('events'));

        $response->assertOk();
        $this->assertContains($theirs->uuid, array_column($response->json('data'), 'uuid'));
    }

    public function test_staff_see_only_the_events_they_are_assigned_to(): void
    {
        $staff = $this->emsUser(EmsRoles::EVENT_STAFF);

        $assigned = $this->event();
        $unassigned = $this->event();

        EventStaff::create([
            'event_id' => $assigned->id,
            'user_id' => $staff->id,
        ]);

        $response = $this->actingAsEms($staff)->getJson($this->url('events'));
        $uuids = array_column($response->json('data'), 'uuid');

        $this->assertContains($assigned->uuid, $uuids);
        $this->assertNotContains($unassigned->uuid, $uuids);

        $this->actingAsEms($staff)
            ->getJson($this->url("events/{$assigned->uuid}"))
            ->assertOk();

        $this->actingAsEms($staff)
            ->getJson($this->url("events/{$unassigned->uuid}"))
            ->assertForbidden();
    }

    public function test_reading_the_access_model_requires_the_system_view_permission(): void
    {
        $this->actingAsEms($this->emsUser(EmsRoles::EVENT_ORGANIZER))
            ->getJson($this->url('roles'))
            ->assertForbidden();

        $this->actingAsEms($this->emsUser(EmsRoles::EVENT_ADMINISTRATOR))
            ->getJson($this->url('roles'))
            ->assertOk();

        $this->actingAsEms($this->emsUser(EmsRoles::EVENT_ADMINISTRATOR))
            ->getJson($this->url('permissions'))
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_authorization_failures_are_written_to_the_audit_trail(): void
    {
        $this->actingAsEms($this->emsUser(EmsRoles::ATTENDEE))
            ->getJson($this->url('events'))
            ->assertForbidden();

        $this->assertDatabaseHas('audit_logs', ['action' => 'ems.request.forbidden']);
    }
}
