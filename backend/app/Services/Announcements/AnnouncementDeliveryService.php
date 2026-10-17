<?php

namespace App\Services\Announcements;

use App\Jobs\Announcements\PrepareAnnouncementDispatchJob;
use App\Jobs\Announcements\SendAnnouncementDeliveriesJob;
use App\Mail\NewsletterAnnouncement;
use App\Models\AnnouncementDelivery;
use App\Models\AnnouncementDispatch;
use App\Models\CMS\Announcement;
use App\Models\NewsletterSubscriber;
use App\Models\User;
use App\Notifications\NewAnnouncementNotification;
use App\Platform\Enums\AlertSeverity;
use App\Services\Notifications\CustomDatabaseChannel;
use App\Services\Notifications\WebPushChannel;
use App\Services\Platform\PlatformAlertService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;
use Throwable;

/**
 * Durable, per-recipient announcement delivery.
 *
 * Guarantee: each recipient/channel row is delivered at least once and is never
 * re-attempted after it is recorded as sent. A duplicate is only possible when the
 * external transport accepted a message but the worker died before recording it,
 * and the row's lease then expired. In-app notifications are written with a
 * deterministic id, so even that case cannot duplicate them.
 */
class AnnouncementDeliveryService
{
    public const EMAIL_CAP_UNLIMITED = 'unlimited';

    private const EMAIL_SENDER_LOCK = 'announcements:email-sender';

    private bool $emailThrottled = false;

    private const CHANNEL_CLASSES = [
        AnnouncementDelivery::CHANNEL_IN_APP => CustomDatabaseChannel::class,
        AnnouncementDelivery::CHANNEL_EMAIL => 'mail',
        AnnouncementDelivery::CHANNEL_PUSH => WebPushChannel::class,
    ];

    /**
     * Start (or resume) the logical send identified by $dedupeKey.
     *
     * @param  array{title: string, excerpt: string, slug: string, audience: string}  $content
     */
    public function start(string $kind, string $dedupeKey, array $content, ?int $announcementId = null, ?int $actorId = null): AnnouncementDispatch
    {
        $now = now();

        DB::table('announcement_dispatches')->insertOrIgnore([
            'uuid' => (string) Str::uuid(),
            'dedupe_key' => $dedupeKey,
            'announcement_id' => $announcementId,
            'kind' => $kind,
            'audience' => $content['audience'],
            'title' => Str::limit($content['title'], 250, ''),
            'excerpt' => $content['excerpt'],
            'slug' => Str::limit($content['slug'], 250, ''),
            'status' => AnnouncementDispatch::STATUS_PREPARING,
            'created_by' => $actorId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        /** @var AnnouncementDispatch $dispatch */
        $dispatch = AnnouncementDispatch::query()->where('dedupe_key', $dedupeKey)->firstOrFail();

        if ($dispatch->isActive()) {
            $this->queuePreparation($dispatch);
        }

        return $dispatch;
    }

    public function queuePreparation(AnnouncementDispatch $dispatch): void
    {
        PrepareAnnouncementDispatchJob::dispatch($dispatch->id)
            ->onQueue((string) config('announcements.delivery.queue', 'default'));
    }

    public function queueSending(AnnouncementDispatch $dispatch, ?\DateTimeInterface $delay = null): void
    {
        SendAnnouncementDeliveriesJob::dispatch($dispatch->id)
            ->onQueue((string) config('announcements.delivery.queue', 'default'))
            ->delay($delay);
    }

    /**
     * Materialise one row per recipient/channel. Idempotent: rerunning after a crash
     * only inserts missing rows.
     */
    public function prepare(AnnouncementDispatch $dispatch): void
    {
        if ($dispatch->status !== AnnouncementDispatch::STATUS_PREPARING) {
            return;
        }

        $probe = $this->notificationFor($dispatch);
        $now = now();

        $users = User::query()->where('is_active', true);
        if ($dispatch->audience !== 'All') {
            $users->whereHas('roles', function ($q) use ($dispatch) {
                $q->where('slug', strtolower($dispatch->audience))->orWhere('name', $dispatch->audience);
            });
        }

        $users->with('notificationPreferences')->chunkById(500, function ($chunk) use ($dispatch, $probe, $now) {
            $rows = [];
            foreach ($chunk as $user) {
                foreach ($this->channelsFor($probe, $user) as $channel) {
                    $rows[] = $this->deliveryRow($dispatch, AnnouncementDelivery::RECIPIENT_USER, $user->id, $channel, $now);
                }
            }
            if ($rows !== []) {
                DB::table('announcement_deliveries')->insertOrIgnore($rows);
            }
        });

        // Newsletter subscribers are the public list; role-targeted sends never reach them.
        if ($dispatch->audience === 'All') {
            NewsletterSubscriber::query()->where('is_active', true)->select('id')
                ->chunkById(1000, function ($chunk) use ($dispatch, $now) {
                    DB::table('announcement_deliveries')->insertOrIgnore($chunk->map(fn ($s) => $this->deliveryRow(
                        $dispatch, AnnouncementDelivery::RECIPIENT_SUBSCRIBER, $s->id, AnnouncementDelivery::CHANNEL_EMAIL, $now
                    ))->all());
                });
        }

        AnnouncementDispatch::query()
            ->whereKey($dispatch->id)
            ->where('status', AnnouncementDispatch::STATUS_PREPARING)
            ->update([
                'status' => AnnouncementDispatch::STATUS_SENDING,
                'recipients_total' => AnnouncementDelivery::query()->where('dispatch_id', $dispatch->id)->count(),
                'prepared_at' => now(),
                'updated_at' => now(),
            ]);

        $dispatch->refresh();
        Log::info('announcement.dispatch.prepared', [
            'dispatch_uuid' => $dispatch->uuid,
            'kind' => $dispatch->kind,
            'recipients' => $dispatch->recipients_total,
        ]);
    }

    /**
     * Deliver claimable rows within a bounded budget. Returns true when claimable work
     * remains (the caller should continue); retries scheduled for later are left to
     * the recovery sweep.
     */
    public function sendDue(AnnouncementDispatch $dispatch): bool
    {
        if ($dispatch->status !== AnnouncementDispatch::STATUS_SENDING) {
            return false;
        }

        $this->emailThrottled = false;
        $timeBudget = max(5, (int) config('announcements.delivery.time_budget_seconds', 30));
        $deadline = microtime(true) + $timeBudget;
        $batch = max(1, (int) config('announcements.delivery.batch_size', 200));
        $interval = max(0, (int) config('announcements.delivery.email_min_interval_seconds', 0));

        // The trailing-hour count is only accurate while no other process is sending
        // capped email, so concurrent senders take turns; a loser sends no email this run.
        $senderLock = null;
        $cap = self::emailHourlyCap();
        if ($cap === false) {
            $this->reportUnsafeEmailCap();
            $emailBudget = 0;
        } elseif ($cap === null) {
            $emailBudget = null;
        } else {
            $senderLock = Cache::lock(self::EMAIL_SENDER_LOCK, $timeBudget + 60);
            if ($senderLock->get()) {
                $emailBudget = $this->emailBudget($cap);
            } else {
                $senderLock = null;
                $emailBudget = 0;
            }
        }

        try {
            return $this->sendDueWithin($dispatch, $deadline, $batch, $interval, $emailBudget);
        } finally {
            $senderLock?->release();
        }
    }

    private function sendDueWithin(AnnouncementDispatch $dispatch, float $deadline, int $batch, int $interval, ?int $emailBudget): bool
    {
        $probe = $this->notificationFor($dispatch);

        $rows = AnnouncementDelivery::query()
            ->where('dispatch_id', $dispatch->id)
            ->claimable()
            ->when($emailBudget === 0, fn ($q) => $q->where('channel', '!=', AnnouncementDelivery::CHANNEL_EMAIL))
            ->orderBy('id')
            ->limit($batch)
            ->get(['id', 'channel']);

        $lastEmailAt = null;
        foreach ($rows as $row) {
            if (microtime(true) >= $deadline) {
                break;
            }

            $isEmail = $row->channel === AnnouncementDelivery::CHANNEL_EMAIL;
            if ($isEmail) {
                if ($emailBudget === 0) {
                    continue;
                }
                if ($interval > 0 && $lastEmailAt !== null) {
                    $wait = $lastEmailAt + $interval - microtime(true);
                    if ($wait > 0) {
                        if (microtime(true) + $wait >= $deadline) {
                            break;
                        }
                        usleep((int) ($wait * 1_000_000));
                    }
                }
            }

            if (! AnnouncementDelivery::claim((int) $row->id)) {
                continue;
            }

            /** @var AnnouncementDelivery $delivery */
            $delivery = AnnouncementDelivery::query()->findOrFail($row->id);
            $this->deliver($dispatch, $delivery, $probe);

            if ($isEmail) {
                $lastEmailAt = microtime(true);
                if ($emailBudget !== null) {
                    $emailBudget--;
                }
            }
        }

        $this->refreshStatus($dispatch);

        $remaining = AnnouncementDelivery::query()->where('dispatch_id', $dispatch->id)->claimable();
        if ($emailBudget === 0) {
            $this->emailThrottled = (clone $remaining)->where('channel', AnnouncementDelivery::CHANNEL_EMAIL)->exists();
            $remaining->where('channel', '!=', AnnouncementDelivery::CHANNEL_EMAIL);
        }

        return $remaining->exists();
    }

    /**
     * Whether the last sendDue() left email rows waiting on the hourly cap.
     */
    public function emailThrottled(): bool
    {
        return $this->emailThrottled;
    }

    /**
     * The configured hourly announcement email cap: a positive integer, null when the
     * operator explicitly chose "unlimited", or false when it is missing or invalid.
     */
    public static function emailHourlyCap(): int|false|null
    {
        $raw = config('announcements.delivery.email_hourly_cap');

        if (is_int($raw)) {
            return $raw > 0 ? $raw : false;
        }
        if (! is_string($raw)) {
            return false;
        }

        $raw = trim($raw);
        if (strcasecmp($raw, self::EMAIL_CAP_UNLIMITED) === 0) {
            return null;
        }

        return ctype_digit($raw) && (int) $raw > 0 ? (int) $raw : false;
    }

    private function reportUnsafeEmailCap(): void
    {
        Log::warning('announcement.email_cap.unconfigured');

        app(PlatformAlertService::class)->createDeduplicatedAlert(
            'announcements.email_cap_unconfigured',
            'platform',
            AlertSeverity::CRITICAL,
            'Announcement email is on hold: hourly cap not configured',
            'ANNOUNCEMENT_EMAIL_HOURLY_CAP is missing or invalid, so announcement emails are held (in-app and push delivery continue). Set it to a positive number below the mail host\'s hourly limit, or to "unlimited" if the host has none.',
        );
    }

    /**
     * Remaining announcement email attempts allowed in the trailing hour. Failed
     * attempts count: the mail host saw them.
     */
    private function emailBudget(int $cap): int
    {
        $used = AnnouncementDelivery::query()
            ->where('channel', AnnouncementDelivery::CHANNEL_EMAIL)
            ->where('last_attempt_at', '>=', now()->subHour())
            ->count();

        return max(0, $cap - $used);
    }

    public function refreshStatus(AnnouncementDispatch $dispatch): void
    {
        if ($dispatch->status !== AnnouncementDispatch::STATUS_SENDING) {
            return;
        }

        if (AnnouncementDelivery::query()->where('dispatch_id', $dispatch->id)->outstanding()->exists()) {
            return;
        }

        $permanent = AnnouncementDelivery::query()
            ->where('dispatch_id', $dispatch->id)
            ->whereIn('status', [AnnouncementDelivery::STATUS_PERMANENTLY_FAILED, AnnouncementDelivery::STATUS_FAILED])
            ->count();

        $updated = AnnouncementDispatch::query()
            ->whereKey($dispatch->id)
            ->where('status', AnnouncementDispatch::STATUS_SENDING)
            ->update([
                'status' => $permanent > 0 ? AnnouncementDispatch::STATUS_COMPLETED_WITH_FAILURES : AnnouncementDispatch::STATUS_COMPLETED,
                'completed_at' => now(),
                'updated_at' => now(),
            ]);

        $dispatch->refresh();

        if ($updated === 1 && $permanent > 0) {
            app(PlatformAlertService::class)->createDeduplicatedAlert(
                'announcements.delivery_failures.'.$dispatch->uuid,
                'platform',
                AlertSeverity::WARNING,
                'Announcement delivery has permanent failures',
                "{$permanent} announcement deliveries failed permanently. Review the dispatch and retry failed deliveries if appropriate.",
                ['dispatch_uuid' => $dispatch->uuid, 'permanent_failures' => $permanent],
            );
        }
    }

    /**
     * Intentional administrative recovery of permanently failed rows. Distinct from the
     * automatic retry: attempts restart and manual_retries is incremented.
     */
    public function retryPermanentFailures(AnnouncementDispatch $dispatch, ?int $actorId): int
    {
        $reset = DB::transaction(function () use ($dispatch) {
            /** @var AnnouncementDispatch $locked */
            $locked = AnnouncementDispatch::query()->whereKey($dispatch->id)->lockForUpdate()->firstOrFail();

            $count = AnnouncementDelivery::query()
                ->where('dispatch_id', $locked->id)
                ->where('status', AnnouncementDelivery::STATUS_PERMANENTLY_FAILED)
                ->update([
                    'status' => AnnouncementDelivery::STATUS_PENDING,
                    'attempts' => 0,
                    'manual_retries' => DB::raw('manual_retries + 1'),
                    'next_attempt_at' => null,
                    'updated_at' => now(),
                ]);

            if ($count > 0) {
                $locked->status = AnnouncementDispatch::STATUS_SENDING;
                $locked->completed_at = null;
                $locked->save();
            }

            return $count;
        });

        $dispatch->refresh();

        if ($reset > 0) {
            Log::info('announcement.dispatch.manual_retry', [
                'dispatch_uuid' => $dispatch->uuid,
                'rows' => $reset,
                'actor_id' => $actorId,
            ]);
            $this->queueSending($dispatch);
        }

        return $reset;
    }

    /**
     * Intentional resend of a published announcement to every current recipient.
     * Serialised on the announcement row so concurrent requests cannot start two.
     *
     * @return AnnouncementDispatch|null null when a resend is already in progress
     */
    public function resend(Announcement $announcement, ?int $actorId): ?AnnouncementDispatch
    {
        $dispatch = DB::transaction(function () use ($announcement, $actorId) {
            Announcement::query()->whereKey($announcement->id)->lockForUpdate()->firstOrFail();

            $active = AnnouncementDispatch::query()
                ->where('announcement_id', $announcement->id)
                ->where('kind', AnnouncementDispatch::KIND_RESEND)
                ->whereIn('status', AnnouncementDispatch::ACTIVE_STATUSES)
                ->exists();

            if ($active) {
                return null;
            }

            return AnnouncementDispatch::query()->create([
                'uuid' => (string) Str::uuid(),
                'dedupe_key' => 'resend:'.Str::uuid(),
                'announcement_id' => $announcement->id,
                'kind' => AnnouncementDispatch::KIND_RESEND,
                'audience' => 'All',
                'title' => Str::limit($announcement->title, 250, ''),
                'excerpt' => $this->excerptFor($announcement),
                'slug' => $announcement->slug,
                'status' => AnnouncementDispatch::STATUS_PREPARING,
                'created_by' => $actorId,
            ]);
        });

        if ($dispatch !== null) {
            $this->queuePreparation($dispatch);
        }

        return $dispatch;
    }

    public function excerptFor(Announcement $announcement): string
    {
        return $announcement->summary ?: Str::limit(strip_tags((string) $announcement->content), 150);
    }

    private function deliver(AnnouncementDispatch $dispatch, AnnouncementDelivery $delivery, NewAnnouncementNotification $probe): void
    {
        $attempt = $delivery->attempts;

        try {
            $outcome = $delivery->recipient_type === AnnouncementDelivery::RECIPIENT_SUBSCRIBER
                ? $this->deliverToSubscriber($dispatch, $delivery)
                : $this->deliverToUser($dispatch, $delivery, $probe);

            $this->finish($delivery, $attempt, [
                'status' => $outcome,
                'sent_at' => $outcome === AnnouncementDelivery::STATUS_SENT ? now() : null,
                'last_error' => null,
                'next_attempt_at' => null,
            ]);
        } catch (Throwable $e) {
            $permanent = $attempt >= AnnouncementDelivery::maxAttempts();

            $this->finish($delivery, $attempt, [
                'status' => $permanent ? AnnouncementDelivery::STATUS_PERMANENTLY_FAILED : AnnouncementDelivery::STATUS_FAILED,
                'failed_at' => now(),
                'next_attempt_at' => $permanent ? null : AnnouncementDelivery::backoffAfter($attempt),
                'last_error' => Str::limit($e::class, 250, ''),
            ]);

            Log::warning('announcement.delivery.failed', [
                'dispatch_uuid' => $dispatch->uuid,
                'delivery_id' => $delivery->id,
                'channel' => $delivery->channel,
                'attempt' => $attempt,
                'permanent' => $permanent,
                'exception' => $e::class,
            ]);
        }
    }

    /**
     * Record the outcome only while this worker still owns the claim, so a slow worker
     * cannot overwrite a row that was reclaimed after its lease expired.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function finish(AnnouncementDelivery $delivery, int $attempt, array $attributes): void
    {
        AnnouncementDelivery::query()
            ->whereKey($delivery->id)
            ->where('status', AnnouncementDelivery::STATUS_SENDING)
            ->where('attempts', $attempt)
            ->update($attributes + ['updated_at' => now()]);
    }

    private function deliverToUser(AnnouncementDispatch $dispatch, AnnouncementDelivery $delivery, NewAnnouncementNotification $probe): string
    {
        $user = User::query()->with('notificationPreferences')->find($delivery->recipient_id);
        $channelClass = self::CHANNEL_CLASSES[$delivery->channel] ?? null;

        // Re-check at send time: deactivation or an opt-out since preparation wins.
        if ($user === null || ! $user->is_active || $channelClass === null
            || ! in_array($channelClass, $probe->via($user), true)) {
            return AnnouncementDelivery::STATUS_SKIPPED;
        }

        $notification = $this->notificationFor($dispatch);
        $notification->id = Uuid::uuid5(Uuid::NAMESPACE_URL, 'announcement-delivery:'.$delivery->id)->toString();

        try {
            $user->notifyNow($notification, [$channelClass]);
        } catch (UniqueConstraintViolationException) {
            // The in-app row from an earlier attempt already exists.
        }

        return AnnouncementDelivery::STATUS_SENT;
    }

    private function deliverToSubscriber(AnnouncementDispatch $dispatch, AnnouncementDelivery $delivery): string
    {
        $subscriber = NewsletterSubscriber::query()->find($delivery->recipient_id);
        if ($subscriber === null || ! $subscriber->is_active || $dispatch->audience !== 'All') {
            return AnnouncementDelivery::STATUS_SKIPPED;
        }

        $frontend = rtrim((string) config('app.frontend_url'), '/');
        $url = $dispatch->announcement_id !== null ? $frontend.'/announcements/'.$dispatch->slug : $frontend.'/';

        Mail::to($subscriber->email)->send(new NewsletterAnnouncement(
            $subscriber->email,
            $dispatch->title,
            $dispatch->excerpt,
            $url,
        ));

        return AnnouncementDelivery::STATUS_SENT;
    }

    private function notificationFor(AnnouncementDispatch $dispatch): NewAnnouncementNotification
    {
        return new NewAnnouncementNotification($dispatch->title, $dispatch->excerpt, $dispatch->slug, $dispatch->audience);
    }

    /**
     * @return list<string>
     */
    private function channelsFor(NewAnnouncementNotification $probe, User $user): array
    {
        $via = $probe->via($user);
        $channels = [];
        foreach (self::CHANNEL_CLASSES as $channel => $class) {
            if (in_array($class, $via, true)) {
                $channels[] = $channel;
            }
        }

        return $channels;
    }

    /**
     * @return array<string, mixed>
     */
    private function deliveryRow(AnnouncementDispatch $dispatch, string $type, int $id, string $channel, $now): array
    {
        return [
            'dispatch_id' => $dispatch->id,
            'recipient_type' => $type,
            'recipient_id' => $id,
            'channel' => $channel,
            'status' => AnnouncementDelivery::STATUS_PENDING,
            'attempts' => 0,
            'manual_retries' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }
}
