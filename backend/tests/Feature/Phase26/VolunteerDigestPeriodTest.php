<?php

namespace Tests\Feature\Phase26;

use App\Mail\DailyVolunteerDigestMail;
use App\Models\VolunteerDigestRun;
use App\Models\VolunteerRegistration;
use App\Platform\Models\PlatformAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Tests\TestCase;

/**
 * Deterministic digest periods. SQLite runs serially; the MySQL concurrency suite
 * races real processes against the same claim.
 */
class VolunteerDigestPeriodTest extends TestCase
{
    use RefreshDatabase;

    public static bool $smtpDown = false;

    /** @var list<list<string>> emails per sent digest */
    public static array $digests = [];

    protected function setUp(): void
    {
        parent::setUp();

        self::$smtpDown = false;
        self::$digests = [];
        config(['features.volunteer_digest_enabled' => true]);

        Mail::extend('p26-digest', fn () => new class extends AbstractTransport {
            protected function doSend(SentMessage $message): void
            {
                if (VolunteerDigestPeriodTest::$smtpDown) {
                    throw new TransportException('Simulated SMTP outage');
                }
                preg_match_all('/mailto:([^"]+)"/', $message->getOriginalMessage()->getHtmlBody() ?? '', $m);
                VolunteerDigestPeriodTest::$digests[] = array_values(array_unique($m[1]));
            }

            public function __toString(): string
            {
                return 'p26-digest://';
            }
        });
        config(['mail.mailers.p26-digest' => ['transport' => 'p26-digest'], 'mail.default' => 'p26-digest']);
    }

    private function registrationAt(string $at, string $email): VolunteerRegistration
    {
        return VolunteerRegistration::factory()->create(['email' => $email, 'created_at' => Carbon::parse($at)]);
    }

    private function runAt(string $at): int
    {
        $this->travelTo(Carbon::parse($at));

        return $this->artisan('volunteer:send-daily-digest')->run();
    }

    public function test_cutoff_assigns_before_at_and_after_registrations_to_one_period_each(): void
    {
        $this->registrationAt('2026-10-13 23:54:59', 'before@sfu.ca');
        $this->registrationAt('2026-10-13 23:55:00', 'at@sfu.ca');
        $this->registrationAt('2026-10-13 23:55:01', 'after@sfu.ca');

        $this->assertSame(0, $this->runAt('2026-10-13 23:55:30'));
        $this->assertSame(0, $this->runAt('2026-10-14 23:55:05'));

        $this->assertSame([['before@sfu.ca'], ['after@sfu.ca', 'at@sfu.ca']], array_map(function ($d) {
            sort($d);

            return $d;
        }, self::$digests));
    }

    public function test_late_or_repeated_scheduler_runs_land_on_the_same_period(): void
    {
        $this->registrationAt('2026-10-13 10:00:00', 'a@sfu.ca');

        // Cron missed 23:55 and only fires at 00:55 / 01:55 the next day.
        $this->registrationAt('2026-10-14 00:10:00', 'next-period@sfu.ca');
        $this->assertSame(0, $this->runAt('2026-10-14 00:55:00'));
        $this->assertSame(0, $this->runAt('2026-10-14 01:55:00'));

        $this->assertSame([['a@sfu.ca']], self::$digests);
        $this->assertSame(1, VolunteerDigestRun::query()->count());
        $this->assertSame('2026-10-13', VolunteerDigestRun::query()->first()->period_date->toDateString());
    }

    public function test_overlapping_runs_cannot_claim_the_same_period(): void
    {
        $this->registrationAt('2026-10-13 10:00:00', 'a@sfu.ca');
        $this->travelTo(Carbon::parse('2026-10-13 23:55:10'));

        // A concurrent run already holds the period's claim.
        $this->artisan('volunteer:send-daily-digest');
        $run = VolunteerDigestRun::query()->firstOrFail();
        $run->forceFill(['status' => VolunteerDigestRun::STATUS_SENDING, 'sent_at' => null, 'last_attempt_at' => now()])->save();
        self::$digests = [];

        $this->artisan('volunteer:send-daily-digest')->assertExitCode(0);
        $this->assertSame([], self::$digests, 'held claim is not processed twice');
        $this->assertFalse(VolunteerDigestRun::claim($run->id));
    }

    public function test_failure_retries_the_same_rows_without_folding_them_into_the_next_digest(): void
    {
        $this->registrationAt('2026-10-13 10:00:00', 'a@sfu.ca');
        self::$smtpDown = true;
        $this->assertSame(1, $this->runAt('2026-10-13 23:55:00'));

        $run = VolunteerDigestRun::query()->firstOrFail();
        $this->assertSame(VolunteerDigestRun::STATUS_FAILED, $run->status);
        $this->assertNull(VolunteerRegistration::query()->first()->digested_at);

        // Next day's run retries the failed period and sends the new period separately.
        $this->registrationAt('2026-10-14 09:00:00', 'b@sfu.ca');
        self::$smtpDown = false;
        $this->assertSame(0, $this->runAt('2026-10-14 23:55:00'));
        $this->assertSame(0, $this->runAt('2026-10-15 00:55:00'));

        $this->assertSame([['a@sfu.ca'], ['b@sfu.ca']], self::$digests);
        $this->assertSame(VolunteerDigestRun::STATUS_SENT, $run->fresh()->status);
        $this->assertSame(2, $run->fresh()->attempts);
        $this->assertSame(0, VolunteerRegistration::query()->whereNull('digested_at')->count());
    }

    public function test_crash_after_claim_is_recovered_after_the_lease_without_losing_rows(): void
    {
        $this->registrationAt('2026-10-13 10:00:00', 'a@sfu.ca');
        $this->travelTo(Carbon::parse('2026-10-13 23:55:00'));

        // Simulate a worker that claimed the period and assigned rows, then died.
        VolunteerDigestRun::query()->create([
            'batch' => $batch = (string) \Illuminate\Support\Str::uuid(),
            'period_date' => '2026-10-13',
            'cutoff_at' => Carbon::parse('2026-10-13 23:55:00'),
            'status' => VolunteerDigestRun::STATUS_SENDING,
            'attempts' => 1,
            'last_attempt_at' => now(),
        ]);
        VolunteerRegistration::query()->update(['digest_batch' => $batch]);

        $this->assertSame(0, $this->runAt('2026-10-14 00:10:00'));
        $this->assertSame([], self::$digests, 'lease still held');

        $this->assertSame(0, $this->runAt('2026-10-14 00:55:00'));
        $this->assertSame([['a@sfu.ca']], self::$digests);
        $this->assertNotNull(VolunteerRegistration::query()->first()->digested_at);
    }

    public function test_permanent_failure_alerts_and_requires_an_intentional_retry(): void
    {
        $this->registrationAt('2026-10-13 10:00:00', 'a@sfu.ca');
        self::$smtpDown = true;
        foreach (['00', '01', '02', '03', '04'] as $hour) {
            $this->runAt("2026-10-14 {$hour}:55:00");
        }

        $run = VolunteerDigestRun::query()->firstOrFail();
        $this->assertSame(VolunteerDigestRun::STATUS_PERMANENTLY_FAILED, $run->status);
        $this->assertSame(1, PlatformAlert::query()->where('application', 'volunteering')->count());

        self::$smtpDown = false;
        $this->assertSame(0, $this->runAt('2026-10-14 06:55:00'));
        $this->assertSame([], self::$digests, 'automatic runs never retry a permanent failure');

        $this->travelTo(Carbon::parse('2026-10-14 07:00:00'));
        $this->artisan('volunteer:send-daily-digest', ['--retry-failed' => true])->assertExitCode(0);
        $this->assertSame([['a@sfu.ca']], self::$digests);
        $this->assertSame(VolunteerDigestRun::STATUS_SENT, $run->fresh()->status);
    }

    public function test_first_new_digest_labels_rows_from_the_legacy_run_minute(): void
    {
        Mail::fake();
        $this->registrationAt('2026-10-12 23:55:20', 'legacy-minute@sfu.ca');
        $this->registrationAt('2026-10-13 09:00:00', 'normal@sfu.ca');

        $this->runAt('2026-10-13 23:55:00');

        Mail::assertSent(DailyVolunteerDigestMail::class, function (DailyVolunteerDigestMail $mail) {
            $flagged = $mail->registrations->whereIn('id', $mail->possibleRepeatIds)->pluck('email')->all();

            return $flagged === ['legacy-minute@sfu.ca'];
        });
    }

    public function test_previously_digested_rows_are_never_reassigned(): void
    {
        VolunteerRegistration::factory()->create(['email' => 'old@sfu.ca', 'created_at' => Carbon::parse('2026-10-10 10:00:00'), 'digested_at' => Carbon::parse('2026-10-10 23:55:00')]);
        $this->registrationAt('2026-10-13 10:00:00', 'new@sfu.ca');

        $this->runAt('2026-10-13 23:55:00');

        $this->assertSame([['new@sfu.ca']], self::$digests);
    }
}
