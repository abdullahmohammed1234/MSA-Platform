<?php

namespace Tests\Feature\Phase251;

use App\Events\AnnouncementPublishedEvent;
use App\Mail\NewsletterAnnouncement;
use App\Models\CMS\Announcement;
use App\Models\NewsletterSubscriber;
use App\Models\User;
use App\Notifications\NewAnnouncementNotification;
use App\Services\CMS\AnnouncementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class AnnouncementDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private function service(): AnnouncementService
    {
        return app(AnnouncementService::class);
    }

    private function create(string $status, string $title = 'Eid Prayer Times'): Announcement
    {
        return $this->service()->create([
            'title' => $title,
            'content' => '<p>Details</p>',
            'status' => $status,
        ], null);
    }

    public function test_first_publication_notifies_once_and_records_marker(): void
    {
        Event::fake([AnnouncementPublishedEvent::class]);

        $announcement = $this->create('published');

        Event::assertDispatchedTimes(AnnouncementPublishedEvent::class, 1);
        $this->assertNotNull($announcement->fresh()->notified_at);
    }

    public function test_editing_a_published_announcement_does_not_renotify(): void
    {
        Event::fake([AnnouncementPublishedEvent::class]);
        $announcement = $this->create('published');

        $this->service()->update($announcement, ['title' => 'Eid Prayer Times (updated)'], null);
        $this->service()->update($announcement->fresh(), ['content' => '<p>New details</p>'], null);

        Event::assertDispatchedTimes(AnnouncementPublishedEvent::class, 1);
    }

    public function test_unpublish_then_republish_does_not_renotify(): void
    {
        Event::fake([AnnouncementPublishedEvent::class]);
        $announcement = $this->create('published');

        $this->service()->update($announcement, ['status' => 'draft'], null);
        $this->service()->update($announcement->fresh(), ['status' => 'published'], null);

        Event::assertDispatchedTimes(AnnouncementPublishedEvent::class, 1);
        $this->assertSame('published', $announcement->fresh()->status);
    }

    public function test_draft_is_silent_until_first_publish(): void
    {
        Event::fake([AnnouncementPublishedEvent::class]);
        $announcement = $this->create('draft');
        Event::assertNotDispatched(AnnouncementPublishedEvent::class);
        $this->assertNull($announcement->fresh()->notified_at);

        $this->service()->update($announcement, ['status' => 'published'], null);

        Event::assertDispatchedTimes(AnnouncementPublishedEvent::class, 1);
    }

    public function test_two_admins_publishing_the_same_draft_notify_once(): void
    {
        Event::fake([AnnouncementPublishedEvent::class]);
        $draft = $this->create('draft');

        $adminA = Announcement::query()->findOrFail($draft->id);
        $adminB = Announcement::query()->findOrFail($draft->id);

        $this->service()->update($adminA, ['status' => 'published'], null);
        $this->service()->update($adminB, ['status' => 'published'], null);

        Event::assertDispatchedTimes(AnnouncementPublishedEvent::class, 1);
    }

    public function test_failed_fan_out_releases_marker_so_republish_retries(): void
    {
        Notification::fake();
        User::factory()->create(['is_active' => true]);

        $failures = 1;
        Event::listen(AnnouncementPublishedEvent::class, function () use (&$failures) {
            if ($failures-- > 0) {
                throw new \RuntimeException('transient queue outage');
            }
        });

        $draft = $this->create('draft');

        try {
            $this->service()->update($draft, ['status' => 'published'], null);
            $this->fail('fan-out failure should surface to the caller');
        } catch (\RuntimeException) {
        }

        $this->assertNull($draft->fresh()->notified_at, 'failed fan-out must not be recorded as delivered');

        $this->service()->update($draft->fresh(), ['status' => 'draft'], null);
        $this->service()->update($draft->fresh(), ['status' => 'published'], null);

        $this->assertNotNull($draft->fresh()->notified_at);
    }

    public function test_recipients_receive_one_notification_per_announcement(): void
    {
        Notification::fake();
        $member = User::factory()->create(['is_active' => true]);

        $announcement = $this->create('published');
        $this->service()->update($announcement, ['status' => 'draft'], null);
        $this->service()->update($announcement->fresh(), ['status' => 'published'], null);

        $channels = [];
        Notification::assertSentTo($member, NewAnnouncementNotification::class, function ($notification, array $via) use (&$channels) {
            foreach ($via as $channel) {
                $channels[] = $channel;
            }

            return true;
        });
        $this->assertNotEmpty($channels);
        $this->assertSame(array_values(array_unique($channels)), $channels, 'each channel is delivered exactly once');
    }

    public function test_role_targeted_broadcast_does_not_reach_newsletter_subscribers(): void
    {
        Notification::fake();
        Mail::fake();
        NewsletterSubscriber::query()->create([
            'uuid' => (string) Str::uuid(),
            'email' => 'public-subscriber@example.com',
            'is_active' => true,
            'subscribed_at' => now(),
        ]);

        event(new AnnouncementPublishedEvent('Volunteer shift change', 'Internal note', 'volunteer-shift-change', 'Volunteers'));
        Mail::assertNotSent(NewsletterAnnouncement::class);

        event(new AnnouncementPublishedEvent('Open house', 'Everyone welcome', 'open-house', 'All'));
        Mail::assertSent(NewsletterAnnouncement::class, fn ($mail) => $mail->hasTo('public-subscriber@example.com'));
    }
}
