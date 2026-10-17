<?php

namespace Tests\Feature\Phase28;

use App\Jobs\Announcements\RecoverAnnouncementDeliveriesJob;
use App\Models\AnnouncementDelivery;
use App\Models\AnnouncementDispatch;
use App\Models\User;
use App\Platform\Models\PlatformAlert;
use App\Services\Announcements\AnnouncementDeliveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Tests\TestCase;

/**
 * The announcement email cap must be configured before bulk email goes out, and
 * every send path (first attempts, retries, sweeps, concurrent senders) must share it.
 */
class AnnouncementEmailCapTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, int> */
    public static array $delivered = [];

    /** @var list<string> */
    public static array $failFor = [];

    protected function setUp(): void
    {
        parent::setUp();

        self::$delivered = [];
        self::$failFor = [];

        Mail::extend('p28-recording', fn () => new class extends AbstractTransport {
            protected function doSend(SentMessage $message): void
            {
                foreach ($message->getEnvelope()->getRecipients() as $address) {
                    /** @var Address $address */
                    $email = $address->getAddress();
                    if (in_array($email, AnnouncementEmailCapTest::$failFor, true)) {
                        throw new TransportException('Simulated SMTP rejection');
                    }
                    AnnouncementEmailCapTest::$delivered[$email] = (AnnouncementEmailCapTest::$delivered[$email] ?? 0) + 1;
                }
            }

            public function __toString(): string
            {
                return 'p28-recording://';
            }
        });

        config([
            'mail.mailers.p28-recording' => ['transport' => 'p28-recording'],
            'mail.default' => 'p28-recording',
            'queue.default' => 'sync',
        ]);
    }

    /** @return array<string, array{mixed, int|false|null}> */
    public static function capValues(): array
    {
        return [
            'missing' => [null, false],
            'empty' => ['', false],
            'zero string' => ['0', false],
            'zero int' => [0, false],
            'negative' => ['-5', false],
            'negative int' => [-5, false],
            'text' => ['abc', false],
            'exponent' => ['1e3', false],
            'decimal' => ['150.5', false],
            'boolean' => [true, false],
            'positive string' => ['150', 150],
            'padded' => [' 150 ', 150],
            'positive int' => [150, 150],
            'explicit unlimited' => ['unlimited', null],
            'explicit unlimited, any case' => [' Unlimited ', null],
        ];
    }

    #[DataProvider('capValues')]
    public function test_only_a_positive_integer_or_explicit_unlimited_is_a_safe_cap(mixed $raw, int|false|null $expected): void
    {
        config(['announcements.delivery.email_hourly_cap' => $raw]);

        $this->assertSame($expected, AnnouncementDeliveryService::emailHourlyCap());
    }

    private function members(int $count): void
    {
        foreach (range(1, $count) as $i) {
            User::factory()->create(['is_active' => true, 'email' => "member{$i}@example.test"]);
        }
    }

    private function broadcast(): AnnouncementDispatch
    {
        $service = app(AnnouncementDeliveryService::class);
        config(['queue.default' => 'database']);
        $dispatch = $service->start(AnnouncementDispatch::KIND_BROADCAST, 'broadcast:p28-'.Str::uuid(), [
            'title' => 'Jumuah update', 'excerpt' => 'Details', 'slug' => '', 'audience' => 'All',
        ]);
        $service->prepare($dispatch);
        config(['queue.default' => 'sync']);

        return $dispatch->refresh();
    }

    private function sweep(): void
    {
        (new RecoverAnnouncementDeliveriesJob())->handle(app(AnnouncementDeliveryService::class));
    }

    private function emailAttempts(): int
    {
        return (int) AnnouncementDelivery::query()->where('channel', AnnouncementDelivery::CHANNEL_EMAIL)->sum('attempts');
    }

    public function test_missing_cap_holds_email_alerts_once_and_keeps_other_channels_flowing(): void
    {
        config(['announcements.delivery.email_hourly_cap' => null]);
        $this->members(3);
        $dispatch = $this->broadcast();
        $service = app(AnnouncementDeliveryService::class);

        $service->sendDue($dispatch);
        $this->sweep();

        $this->assertSame([], self::$delivered, 'no announcement email may leave without a configured cap');
        $this->assertSame(0, $this->emailAttempts(), 'held rows are never attempted');
        $this->assertTrue($service->emailThrottled());
        $this->assertSame(0, AnnouncementDelivery::query()->where('dispatch_id', $dispatch->id)
            ->where('channel', AnnouncementDelivery::CHANNEL_IN_APP)->where('status', '!=', AnnouncementDelivery::STATUS_SENT)->count());
        $this->assertSame(AnnouncementDispatch::STATUS_SENDING, $dispatch->fresh()->status);

        $alerts = PlatformAlert::query()->where('title', 'like', 'Announcement email is on hold%')->get();
        $this->assertCount(1, $alerts, 'the alert is deduplicated across runs');
        $this->assertSame('critical', $alerts->first()->severity->value);

        // Once an operator configures a cap, the held rows go out exactly once.
        config(['announcements.delivery.email_hourly_cap' => '50']);
        $this->sweep();

        $this->assertSame(3, count(self::$delivered));
        $this->assertSame([1], array_values(array_unique(self::$delivered)));
        $this->assertSame(AnnouncementDispatch::STATUS_COMPLETED, $dispatch->fresh()->status);
    }

    public function test_invalid_cap_is_not_treated_as_unlimited(): void
    {
        config(['announcements.delivery.email_hourly_cap' => '0']);
        $this->members(2);
        $this->broadcast();

        $this->sweep();

        $this->assertSame([], self::$delivered);
        $this->assertSame(0, $this->emailAttempts());
    }

    public function test_explicit_unlimited_sends_everything(): void
    {
        config(['announcements.delivery.email_hourly_cap' => 'unlimited']);
        $this->members(4);
        $dispatch = $this->broadcast();

        $this->sweep();

        $this->assertSame(4, count(self::$delivered));
        $this->assertSame(AnnouncementDispatch::STATUS_COMPLETED, $dispatch->fresh()->status);
        $this->assertSame(0, PlatformAlert::query()->where('title', 'like', 'Announcement email is on hold%')->count());
    }

    public function test_a_sender_that_cannot_take_the_lock_sends_no_email(): void
    {
        config(['announcements.delivery.email_hourly_cap' => 10]);
        $this->members(3);
        $dispatch = $this->broadcast();
        $service = app(AnnouncementDeliveryService::class);

        $otherWorker = Cache::lock('announcements:email-sender', 120);
        $this->assertTrue($otherWorker->get());

        $service->sendDue($dispatch);

        $this->assertSame([], self::$delivered, 'budget is unknown while another sender holds it');
        $this->assertSame(0, $this->emailAttempts());
        $this->assertTrue($service->emailThrottled());
        $this->assertSame(0, AnnouncementDelivery::query()->where('dispatch_id', $dispatch->id)
            ->where('channel', AnnouncementDelivery::CHANNEL_IN_APP)->where('status', '!=', AnnouncementDelivery::STATUS_SENT)->count());

        $otherWorker->release();
        $service->sendDue($dispatch);

        $this->assertSame(3, count(self::$delivered));
        $this->assertTrue(Cache::lock('announcements:email-sender', 1)->get(), 'the sender releases the lock when done');
    }

    public function test_failed_attempts_and_their_retries_count_against_the_cap(): void
    {
        config(['announcements.delivery.email_hourly_cap' => 1]);
        $this->members(3);
        self::$failFor = ['member1@example.test'];
        $this->broadcast();

        $this->sweep();
        $this->assertSame(1, $this->emailAttempts(), 'the failed attempt used the only slot');
        $this->assertSame([], self::$delivered);

        // The failed row's backoff has elapsed, but the hour has not: nothing more is attempted.
        self::$failFor = [];
        $this->travel(2)->minutes();
        $this->sweep();
        $this->assertSame(1, $this->emailAttempts());

        // Each later hour releases exactly one more attempt.
        $this->travel(61)->minutes();
        $this->sweep();
        $this->assertSame(2, $this->emailAttempts());
        $this->assertSame(1, array_sum(self::$delivered));
    }

    /** @return array<string, array{mixed, int, string}> */
    public static function productionCapChecks(): array
    {
        return [
            'missing fails' => [null, 1, 'FAIL: ANNOUNCEMENT_EMAIL_HOURLY_CAP'],
            'zero fails' => ['0', 1, 'FAIL: ANNOUNCEMENT_EMAIL_HOURLY_CAP'],
            'unlimited warns' => ['unlimited', 0, 'WARN: ANNOUNCEMENT_EMAIL_HOURLY_CAP'],
            'positive passes' => ['150', 0, '| ANNOUNCEMENT_EMAIL_HOURLY_CAP   | 150'],
        ];
    }

    #[DataProvider('productionCapChecks')]
    public function test_production_check_refuses_a_missing_or_invalid_cap(mixed $raw, int $expectedExit, string $expectedLine): void
    {
        config([
            'app.env' => 'production', 'app.debug' => false, 'app.url' => 'https://api.example.test',
            'app.frontend_url' => 'https://example.test',
            'queue.default' => 'database', 'announcements.delivery.email_hourly_cap' => $raw,
        ]);

        $exit = \Illuminate\Support\Facades\Artisan::call('msa:production-check');
        $output = \Illuminate\Support\Facades\Artisan::output();

        $this->assertSame($expectedExit, $exit, $output);
        $this->assertStringContainsString($expectedLine, $output);
    }

    public function test_separate_dispatches_share_one_hourly_cap(): void
    {
        config(['announcements.delivery.email_hourly_cap' => 3]);
        $this->members(2);
        $this->broadcast();
        $this->broadcast();

        $this->sweep();
        $this->travel(5)->minutes();
        $this->sweep();

        $this->assertSame(3, $this->emailAttempts(), 'two dispatches x two recipients, capped at three per hour');
        $this->assertSame(3, array_sum(self::$delivered));

        $this->travel(61)->minutes();
        $this->sweep();
        $this->assertSame(4, array_sum(self::$delivered));
        $this->assertSame([2, 2], array_values(self::$delivered), 'each member gets each dispatch exactly once');
    }
}
