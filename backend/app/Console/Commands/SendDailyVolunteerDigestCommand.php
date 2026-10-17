<?php

namespace App\Console\Commands;

use App\Mail\DailyVolunteerDigestMail;
use App\Models\VolunteerDigestRun;
use App\Models\VolunteerRegistration;
use App\Platform\Enums\AlertSeverity;
use App\Services\Platform\PlatformAlertService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Each registration belongs to exactly one digest period: the first period whose
 * cutoff (23:55 app time, exclusive) is after its created_at and that claims it.
 * Assignment is durable before sending, so a crash or SMTP failure retries the same
 * run with the same rows and can never fold them into a second digest.
 */
class SendDailyVolunteerDigestCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'volunteer:send-daily-digest
        {--retry-failed : Intentionally re-arm permanently failed digest runs}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send the volunteer registration digest for each completed period (cutoff 23:55) and retry unsent periods.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! config('features.volunteer_digest_enabled', false)) {
            $this->info('Volunteer digest is disabled (VOLUNTEER_DIGEST_ENABLED).');

            return self::SUCCESS;
        }

        if ($this->option('retry-failed')) {
            $rearmed = VolunteerDigestRun::query()
                ->where('status', VolunteerDigestRun::STATUS_PERMANENTLY_FAILED)
                ->update(['status' => VolunteerDigestRun::STATUS_FAILED, 'attempts' => 0, 'updated_at' => now()]);
            Log::info('volunteer_digest.manual_retry', ['runs' => $rearmed]);
            $this->info("Re-armed {$rearmed} permanently failed digest run(s).");
        }

        $cutoff = VolunteerDigestRun::latestDueCutoff();
        DB::table('volunteer_digest_runs')->insertOrIgnore([
            'batch' => (string) Str::uuid(),
            'period_date' => $cutoff->toDateString(),
            'cutoff_at' => $cutoff,
            'status' => VolunteerDigestRun::STATUS_PENDING,
            'attempts' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $failed = false;
        $runs = VolunteerDigestRun::query()->claimable()->orderBy('period_date')->pluck('id');

        foreach ($runs as $id) {
            if (! VolunteerDigestRun::claim((int) $id)) {
                continue;
            }

            $failed = ! $this->process(VolunteerDigestRun::query()->findOrFail($id)) || $failed;
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function process(VolunteerDigestRun $run): bool
    {
        $attempt = $run->attempts;

        // Rows already assigned to another run are untouched; rows committed after
        // this statement fall to the next period.
        VolunteerRegistration::query()
            ->whereNull('digest_batch')
            ->whereNull('digested_at')
            ->where('created_at', '<', $run->cutoff_at)
            ->update(['digest_batch' => $run->batch]);

        $registrations = VolunteerRegistration::query()
            ->where('digest_batch', $run->batch)
            ->orderByDesc('created_at')
            ->get();

        if ($registrations->isEmpty()) {
            $this->finish($run, $attempt, ['status' => VolunteerDigestRun::STATUS_EMPTY, 'registrations_count' => 0, 'last_error' => null]);
            $this->info("No volunteer registrations for the period ending {$run->cutoff_at->toDateTimeString()}.");

            return true;
        }

        $recipient = config('website.contact_recipient', 'sfumsa@hotmail.com') ?: 'sfumsa@hotmail.com';
        $dateStr = $run->period_date->toDateString();

        try {
            Mail::to($recipient)->send(new DailyVolunteerDigestMail(
                $registrations,
                $dateStr,
                $this->possibleLegacyRepeats($run, $registrations),
            ));
        } catch (Throwable $e) {
            $permanent = $attempt >= VolunteerDigestRun::MAX_ATTEMPTS;
            $this->finish($run, $attempt, [
                'status' => $permanent ? VolunteerDigestRun::STATUS_PERMANENTLY_FAILED : VolunteerDigestRun::STATUS_FAILED,
                'registrations_count' => $registrations->count(),
                'last_error' => Str::limit($e::class, 250, ''),
            ]);

            $this->error('Failed to send daily volunteer digest email: '.$e::class);
            Log::error('Failed to send daily volunteer digest email', [
                'period' => $dateStr,
                'attempt' => $attempt,
                'permanent' => $permanent,
                'exception' => $e::class,
            ]);

            if ($permanent) {
                app(PlatformAlertService::class)->createDeduplicatedAlert(
                    'volunteer_digest.permanently_failed.'.$dateStr,
                    'volunteering',
                    AlertSeverity::WARNING,
                    'Volunteer digest could not be sent',
                    "The volunteer digest for {$dateStr} failed {$attempt} times. Fix mail delivery, then run volunteer:send-daily-digest --retry-failed.",
                    ['period' => $dateStr, 'registrations' => $registrations->count()],
                );
            }

            return false;
        }

        $sentAt = now();
        $this->finish($run, $attempt, [
            'status' => VolunteerDigestRun::STATUS_SENT,
            'registrations_count' => $registrations->count(),
            'sent_at' => $sentAt,
            'last_error' => null,
        ]);
        VolunteerRegistration::query()->where('digest_batch', $run->batch)->update(['digested_at' => $sentAt]);

        $this->info("Successfully sent daily volunteer digest email ({$registrations->count()} applications).");
        Log::info('Sent daily volunteer digest email', ['count' => $registrations->count(), 'period' => $dateStr]);

        return true;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function finish(VolunteerDigestRun $run, int $attempt, array $attributes): void
    {
        VolunteerDigestRun::query()
            ->whereKey($run->id)
            ->where('status', VolunteerDigestRun::STATUS_SENDING)
            ->where('attempts', $attempt)
            ->update($attributes + ['updated_at' => now()]);
    }

    /**
     * The final legacy digest (pre-Phase 26) ran during the 23:55 minute and sent rows
     * created before its own start, which is not recorded. Rows from that minute may
     * therefore have been in it already; the first new digest labels them instead of
     * dropping them.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, VolunteerRegistration>  $registrations
     * @return list<int>
     */
    private function possibleLegacyRepeats(VolunteerDigestRun $run, $registrations): array
    {
        $earlierDelivered = VolunteerDigestRun::query()
            ->where('id', '!=', $run->id)
            ->whereIn('status', [VolunteerDigestRun::STATUS_SENT, VolunteerDigestRun::STATUS_EMPTY])
            ->exists();

        if ($earlierDelivered) {
            return [];
        }

        return $registrations
            ->filter(fn (VolunteerRegistration $r) => $r->created_at !== null && $r->created_at->format('H:i') === '23:55')
            ->pluck('id')
            ->values()
            ->all();
    }
}
