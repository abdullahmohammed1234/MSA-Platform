<?php

namespace Tests\Feature\Security;

use App\Ems\Enums\EventStatus;
use App\Ems\Enums\WaitlistStatus;
use App\Ems\Models\Event;
use App\Ems\Models\TicketType;
use App\Ems\Models\WaitlistEntry;
use App\Ems\Services\WaitlistService;
use Illuminate\Support\Str;
use Tests\Feature\Ems\EmsTestCase;

class WaitlistLeaveOwnershipTest extends EmsTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['queue.default' => 'sync']);
    }

    private function waitingEntry(): array
    {
        $event = Event::factory()->create([
            'category_id' => $this->category(['is_active' => true])->id,
            'slug' => 'waitlist-'.Str::lower(Str::random(6)),
            'capacity' => 1,
            'waitlist_enabled' => true,
            'status' => EventStatus::Live,
            'is_public' => true,
        ]);
        $type = TicketType::factory()->create([
            'event_id' => $event->id,
            'price' => 0,
            'quantity' => 200,
            'is_active' => true,
        ]);

        $entry = app(WaitlistService::class)->join($event, [
            'first_name' => 'Waiting',
            'last_name' => 'Guest',
            'email' => 'owner@example.com',
            'ticket_type_id' => $type->uuid,
            'quantity' => 1,
        ]);

        return [$event, $entry];
    }

    private function leaveUrl(Event $event, WaitlistEntry $entry, string $query = ''): string
    {
        return "/api/v1/ems/public/events/{$event->slug}/waitlist/{$entry->uuid}{$query}";
    }

    public function test_leaving_without_email_is_rejected_and_entry_kept(): void
    {
        [$event, $entry] = $this->waitingEntry();

        $this->deleteJson($this->leaveUrl($event, $entry))->assertStatus(422);

        $this->assertSame(WaitlistStatus::Waiting, $entry->fresh()->status);
    }

    public function test_leaving_with_another_email_is_rejected_and_entry_kept(): void
    {
        [$event, $entry] = $this->waitingEntry();

        $this->deleteJson($this->leaveUrl($event, $entry, '?email=attacker@example.com'))->assertNotFound();

        $this->assertSame(WaitlistStatus::Waiting, $entry->fresh()->status);
    }

    public function test_owner_email_leaves_case_insensitively(): void
    {
        [$event, $entry] = $this->waitingEntry();

        $this->deleteJson($this->leaveUrl($event, $entry, '?email=Owner%40Example.com'))->assertOk();

        $this->assertSame(WaitlistStatus::Left, $entry->fresh()->status);
    }
}
