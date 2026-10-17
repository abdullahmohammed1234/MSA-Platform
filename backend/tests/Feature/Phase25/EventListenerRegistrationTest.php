<?php

namespace Tests\Feature\Phase25;

use App\Events\CourseCompletedEvent;
use App\Models\User;
use App\Notifications\CourseCompletedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class EventListenerRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_domain_event_notifies_each_recipient_once(): void
    {
        Notification::fake();
        $user = User::factory()->create(['is_active' => true]);

        event(new CourseCompletedEvent($user, 'Intro to Islam'));

        Notification::assertSentToTimes($user, CourseCompletedNotification::class, 1);
    }

    public function test_subscriber_handlers_are_registered_once(): void
    {
        $subscriber = \App\Listeners\NotificationEventSubscriber::class;

        foreach ([
            \Illuminate\Notifications\Events\NotificationSent::class => $subscriber,
            \Illuminate\Notifications\Events\NotificationFailed::class => $subscriber,
            \App\Events\CourseCompletedEvent::class => $subscriber,
            \App\Events\AnnouncementPublishedEvent::class => $subscriber,
            \App\Events\TrainingScheduledEvent::class => $subscriber,
            \App\Events\CertificateAwardedEvent::class => $subscriber,
            \App\Ems\Events\RegistrationCreated::class => $subscriber,
            \Illuminate\Console\Events\ScheduledTaskFailed::class => \App\Listeners\ScheduledTaskFailureListener::class,
        ] as $event => $listenerClass) {
            $matching = array_filter(
                Event::getRawListeners()[$event] ?? [],
                fn ($listener) => str_contains(is_array($listener) ? implode('@', $listener) : (string) $listener, $listenerClass)
            );

            $this->assertCount(1, $matching, "{$event} -> {$listenerClass} registrations");
        }
    }

    public function test_event_discovery_is_disabled_so_event_cache_cannot_duplicate_listeners(): void
    {
        $this->artisan('event:cache')->assertExitCode(0);

        try {
            $cached = require $this->app->getCachedEventsPath();
            $this->assertSame([], collect($cached)->flatten()->all(), 'event:cache must not capture discovered listeners');
        } finally {
            $this->artisan('event:clear');
        }
    }

    public function test_every_ems_domain_event_reaches_the_activity_subscriber_exactly_once(): void
    {
        $events = $this->concreteSubclassesIn(app_path('Ems/Events'), 'App\\Ems\\Events\\', \App\Ems\Events\EmsDomainEvent::class);
        $this->assertNotEmpty($events);

        foreach ($events as $event) {
            $this->assertSame(1, $this->registrationCount($event, \App\Ems\Listeners\EmsActivitySubscriber::class), "{$event} -> EmsActivitySubscriber registrations");
        }
    }

    public function test_every_listener_class_is_explicitly_registered(): void
    {
        $registered = collect(Event::getRawListeners())->flatten()
            ->map(fn ($listener) => is_string($listener) ? explode('@', $listener)[0] : null)
            ->filter()->unique();

        $listenerClasses = collect(\Illuminate\Support\Facades\File::allFiles(app_path()))
            ->filter(fn ($file) => preg_match('#[\\\\/]Listeners[\\\\/]#', $file->getPathname()))
            ->map(fn ($file) => 'App\\'.str_replace(['/', '.php'], ['\\', ''], str_replace('\\', '/', $file->getRelativePathname())));

        $this->assertNotEmpty($listenerClasses);
        foreach ($listenerClasses as $class) {
            $this->assertContains($class, $registered, "{$class} has no explicit registration (discovery is disabled)");
        }
    }

    public function test_auth_audit_closures_are_registered_once(): void
    {
        foreach ([
            \Illuminate\Auth\Events\Failed::class,
            \Illuminate\Auth\Events\Login::class,
            \Illuminate\Auth\Events\Logout::class,
        ] as $event) {
            $appClosures = array_filter(
                Event::getRawListeners()[$event] ?? [],
                fn ($listener) => $listener instanceof \Closure
                    && str_contains((string) (new \ReflectionFunction($listener))->getFileName(), 'AppServiceProvider')
            );

            $this->assertCount(1, $appClosures, "{$event} audit closure registrations");
        }
    }

    public function test_cached_and_uncached_registrations_are_identical_at_boot(): void
    {
        $uncached = $this->listedEventsFromFreshProcess();

        $this->artisan('event:cache')->assertExitCode(0);
        try {
            $cached = $this->listedEventsFromFreshProcess();
        } finally {
            $this->artisan('event:clear');
        }

        $this->assertNotEmpty($uncached);
        $this->assertEquals($uncached, $cached);
    }

    private function registrationCount(string $event, string $listenerClass): int
    {
        return count(array_filter(
            Event::getRawListeners()[$event] ?? [],
            fn ($listener) => str_contains(is_array($listener) ? implode('@', $listener) : (is_string($listener) ? $listener : ''), $listenerClass)
        ));
    }

    /**
     * @return list<class-string>
     */
    private function concreteSubclassesIn(string $directory, string $namespace, string $parent): array
    {
        return collect(\Illuminate\Support\Facades\File::files($directory))
            ->map(fn ($file) => $namespace.$file->getBasename('.php'))
            ->filter(fn ($class) => is_subclass_of($class, $parent) && ! (new \ReflectionClass($class))->isAbstract())
            ->values()->all();
    }

    /**
     * @return array<string, list<string>>
     */
    private function listedEventsFromFreshProcess(): array
    {
        $process = new \Symfony\Component\Process\Process(
            [PHP_BINARY, 'artisan', 'event:list', '--json', '--no-ansi'],
            base_path(),
            ['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'DB_URL' => '', 'APP_ENV' => 'testing']
        );
        $process->mustRun();

        $listed = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);

        return collect($listed)
            ->filter(fn ($row) => str_starts_with($row['event'], 'App\\')
                || collect($row['listeners'])->contains(fn ($l) => str_contains($l, 'App\\') || str_contains($l, 'app/Providers') || str_contains($l, 'app\\Providers')))
            ->mapWithKeys(fn ($row) => [$row['event'] => $row['listeners']])
            ->sortKeys()->all();
    }
}
