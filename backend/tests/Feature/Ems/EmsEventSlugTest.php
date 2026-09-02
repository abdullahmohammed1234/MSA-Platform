<?php

namespace Tests\Feature\Ems;

use App\Ems\Enums\EventStatus;
use App\Ems\Models\Event;
use App\Ems\Support\EmsRoles;

class EmsEventSlugTest extends EmsTestCase
{
    private function adminUser()
    {
        return $this->emsUser(EmsRoles::EVENT_ADMINISTRATOR);
    }

    public function test_creation_without_slug_generates_automatic_slug(): void
    {
        $user = $this->adminUser();

        $response = $this->actingAsEms($user)->postJson($this->url('events'), [
            'name' => 'SFU MSA Welcome Dinner 2026',
            'start_at' => now()->addDays(7)->toIso8601String(),
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.slug', 'sfu-msa-welcome-dinner-2026');
        $response->assertJsonPath('data.is_slug_custom', false);
        $response->assertJsonPath('data.slug_mode', 'auto');

        $this->assertDatabaseHas('ems_events', [
            'name' => 'SFU MSA Welcome Dinner 2026',
            'slug' => 'sfu-msa-welcome-dinner-2026',
            'is_slug_custom' => false,
        ]);
    }

    public function test_creation_with_explicit_slug_sets_manual_mode(): void
    {
        $user = $this->adminUser();

        $response = $this->actingAsEms($user)->postJson($this->url('events'), [
            'name' => 'SFU MSA Welcome Dinner 2026',
            'slug' => 'welcome-dinner-2026',
            'slug_mode' => 'manual',
            'start_at' => now()->addDays(7)->toIso8601String(),
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.slug', 'welcome-dinner-2026');
        $response->assertJsonPath('data.is_slug_custom', true);
        $response->assertJsonPath('data.slug_mode', 'manual');

        $this->assertDatabaseHas('ems_events', [
            'name' => 'SFU MSA Welcome Dinner 2026',
            'slug' => 'welcome-dinner-2026',
            'is_slug_custom' => true,
        ]);
    }

    public function test_title_update_in_auto_mode_synchronizes_slug(): void
    {
        $user = $this->adminUser();

        $event = Event::factory()->create([
            'name' => 'SFU MSA Welcome Dinner',
            'slug' => 'sfu-msa-welcome-dinner',
            'is_slug_custom' => false,
        ]);

        $response = $this->actingAsEms($user)->putJson($this->url("events/{$event->uuid}"), [
            'name' => 'SFU MSA Annual Welcome Dinner',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.slug', 'sfu-msa-annual-welcome-dinner');
        $response->assertJsonPath('data.is_slug_custom', false);

        $this->assertDatabaseHas('ems_events', [
            'id' => $event->id,
            'name' => 'SFU MSA Annual Welcome Dinner',
            'slug' => 'sfu-msa-annual-welcome-dinner',
            'is_slug_custom' => false,
        ]);
    }

    public function test_title_update_in_manual_mode_preserves_custom_slug(): void
    {
        $user = $this->adminUser();

        $event = Event::factory()->create([
            'name' => 'SFU MSA Annual Welcome Dinner',
            'slug' => 'welcome-dinner',
            'is_slug_custom' => true,
        ]);

        $response = $this->actingAsEms($user)->putJson($this->url("events/{$event->uuid}"), [
            'name' => 'SFU MSA Annual Welcome Dinner & Fundraiser',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.slug', 'welcome-dinner');
        $response->assertJsonPath('data.is_slug_custom', true);

        $this->assertDatabaseHas('ems_events', [
            'id' => $event->id,
            'name' => 'SFU MSA Annual Welcome Dinner & Fundraiser',
            'slug' => 'welcome-dinner',
            'is_slug_custom' => true,
        ]);
    }

    public function test_returning_to_auto_mode_regenerates_slug_from_title(): void
    {
        $user = $this->adminUser();

        $event = Event::factory()->create([
            'name' => 'SFU MSA Annual Welcome Dinner & Fundraiser',
            'slug' => 'welcome-dinner',
            'is_slug_custom' => true,
        ]);

        $response = $this->actingAsEms($user)->putJson($this->url("events/{$event->uuid}"), [
            'slug_mode' => 'auto',
            'reset_slug' => true,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.slug', 'sfu-msa-annual-welcome-dinner-fundraiser');
        $response->assertJsonPath('data.is_slug_custom', false);

        $this->assertDatabaseHas('ems_events', [
            'id' => $event->id,
            'slug' => 'sfu-msa-annual-welcome-dinner-fundraiser',
            'is_slug_custom' => false,
        ]);
    }

    public function test_duplicate_auto_slug_appends_suffix(): void
    {
        $user = $this->adminUser();

        Event::factory()->create([
            'name' => 'SFU MSA Dinner',
            'slug' => 'sfu-msa-dinner',
        ]);

        $response = $this->actingAsEms($user)->postJson($this->url('events'), [
            'name' => 'SFU MSA Dinner',
            'start_at' => now()->addDays(7)->toIso8601String(),
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.slug', 'sfu-msa-dinner-2');
    }

    public function test_duplicate_manual_slug_returns_422_validation_error(): void
    {
        $user = $this->adminUser();

        Event::factory()->create([
            'name' => 'Existing Event',
            'slug' => 'custom-dinner-slug',
        ]);

        $response = $this->actingAsEms($user)->postJson($this->url('events'), [
            'name' => 'New Event',
            'slug' => 'custom-dinner-slug',
            'slug_mode' => 'manual',
            'start_at' => now()->addDays(7)->toIso8601String(),
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['slug']);
    }

    public function test_reserved_slug_returns_422_validation_error(): void
    {
        $user = $this->adminUser();

        $response = $this->actingAsEms($user)->postJson($this->url('events'), [
            'name' => 'Admin Event',
            'slug' => 'admin',
            'slug_mode' => 'manual',
            'start_at' => now()->addDays(7)->toIso8601String(),
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['slug']);
    }

    public function test_published_event_slug_can_be_updated_safely(): void
    {
        $user = $this->adminUser();

        $event = Event::factory()->publiclyDiscoverable()->create([
            'name' => 'Published Event',
            'slug' => 'published-event',
            'status' => EventStatus::RegistrationOpen,
        ]);

        $response = $this->actingAsEms($user)->putJson($this->url("events/{$event->uuid}"), [
            'slug' => 'new-published-event-slug',
            'slug_mode' => 'manual',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.slug', 'new-published-event-slug');
    }

    public function test_public_event_resolution_by_slug_works(): void
    {
        $event = Event::factory()->publiclyDiscoverable()->create([
            'name' => 'Public Showcase Event',
            'slug' => 'public-showcase-event',
            'status' => EventStatus::RegistrationOpen,
            'is_public' => true,
        ]);

        $response = $this->getJson($this->url("public/events/{$event->slug}"));
        $response->assertOk();
        $response->assertJsonPath('data.uuid', $event->uuid);
    }
}
