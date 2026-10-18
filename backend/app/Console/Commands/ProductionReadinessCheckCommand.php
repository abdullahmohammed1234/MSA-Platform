<?php

namespace App\Console\Commands;

use App\Services\Announcements\AnnouncementDeliveryService;
use App\Support\ScheduleCadence;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Offline production readiness checks — no secrets printed, no external sends.
 *
 * Usage (on the backend host after deploy):
 *   php artisan msa:production-check
 */
class ProductionReadinessCheckCommand extends Command
{
    protected $signature = 'msa:production-check {--strict : Exit non-zero when any check fails}';

    protected $description = 'Verify SFU MSA Platform production configuration safety (no secrets, no mail/payment sends)';

    public function handle(): int
    {
        $failures = [];
        $warnings = [];

        $env = (string) config('app.env');
        $debug = (bool) config('app.debug');
        $url = (string) config('app.url');
        $queue = (string) config('queue.default');
        $mailer = (string) config('mail.default');
        $forceHttps = (bool) config('app.force_https');
        $secureCookie = (bool) config('session.secure');

        if ($env === 'production') {
            if ($debug) {
                $failures[] = 'APP_DEBUG must be false in production';
            }
            if ($queue === 'sync') {
                $failures[] = 'QUEUE_CONNECTION must not be sync in production (use database/redis + workers)';
            }
            if (! str_starts_with($url, 'https://')) {
                $failures[] = 'APP_URL should use https:// in production';
            }
            $frontendUrl = (string) config('app.frontend_url');
            $frontendHost = (string) parse_url($frontendUrl, PHP_URL_HOST);
            if (! str_starts_with($frontendUrl, 'https://') || in_array($frontendHost, ['localhost', '127.0.0.1', '[::1]', '::1'], true)) {
                $failures[] = 'FRONTEND_URL must be the public https:// site origin in production (it drives CORS, Sanctum and email links)';
            }
            $connectUrl = (string) config('app.connect_url');
            $connectHost = (string) parse_url($connectUrl, PHP_URL_HOST);
            if (! str_starts_with($connectUrl, 'https://') || in_array($connectHost, ['localhost', '127.0.0.1', '[::1]', '::1'], true)) {
                $warnings[] = 'CONNECT_URL should be the public https:// Connect origin in production (required for Connect CORS, Sanctum, and notification links)';
            }
            if (! $forceHttps) {
                $warnings[] = 'FORCE_HTTPS is false — enable when HTTPS is terminated at the reverse proxy';
            }
            if (! $secureCookie) {
                $warnings[] = 'SESSION_SECURE_COOKIE is false — enable for HTTPS production';
            }
            if (in_array($mailer, ['log', 'array', ''], true)) {
                $warnings[] = "MAIL_MAILER=\"{$mailer}\" will not deliver email in production — configure SMTP";
            }
            if ((bool) config('ems.payments.enabled') && empty(config('ems.payments.square.access_token'))) {
                $warnings[] = 'EMS payments enabled but Square access token appears unset';
            }
            if (! ScheduleCadence::isSupported((int) config('app.scheduler_cron_interval_minutes', 1))) {
                $failures[] = 'SCHEDULER_CRON_INTERVAL_MINUTES must be one of '.implode(', ', ScheduleCadence::VALID_INTERVALS).' and match the cron entry; other values leave tasks that the cron never reaches';
            }
            $emailCap = AnnouncementDeliveryService::emailHourlyCap();
            if ($emailCap === false) {
                $failures[] = 'ANNOUNCEMENT_EMAIL_HOURLY_CAP must be a positive number below the mail host\'s hourly limit (announcement email is held until it is)';
            } elseif ($emailCap === null) {
                $warnings[] = 'ANNOUNCEMENT_EMAIL_HOURLY_CAP is "unlimited" — only correct when the mail host has no hourly cap';
            }
        } else {
            $this->line("Environment: {$env} (strict production rules apply only when APP_ENV=production)");
        }

        if (! Schema::hasTable('jobs') || ! Schema::hasTable('failed_jobs')) {
            $failures[] = 'Queue tables jobs/failed_jobs missing — run migrations';
        }

        if (! Schema::hasTable('users') || ! Schema::hasTable('roles') || ! Schema::hasTable('permissions')) {
            $failures[] = 'Identity/RBAC tables missing';
        }

        // Archived CMS event tables must remain (Phase 9) — never drop.
        if (! Schema::hasTable('events') || ! Schema::hasTable('event_registrations')) {
            $warnings[] = 'Archived CMS event tables missing (unexpected; Phase 9 retains them)';
        }

        if (config('cms.legacy_events.drop_schema') === true) {
            $failures[] = 'cms.legacy_events.drop_schema must remain false';
        }

        $apps = array_keys(config('systems.applications', []));
        sort($apps);
        $expectedApps = [
            'cms',
            'connect',
            'dams',
            'dawah-academy',
            'donations',
            'ems',
            'main-website',
            'mlibms',
            'sponsorship',
            'store',
            'vms',
        ];
        if ($apps !== $expectedApps) {
            $failures[] = 'Systems registry must contain the registered platform applications, including connect';
        }

        $v1Scope = array_values(config('systems.v1_release_scope', []));
        sort($v1Scope);
        $outsideV1 = array_values(array_diff($apps, $v1Scope));
        if ($outsideV1 !== []) {
            $warnings[] = 'Registered outside the approved V1 release commitment (still registered, not disabled): '.implode(', ', $outsideV1);
        }

        $this->info('SFU MSA Platform — production readiness check');
        $this->table(['Setting', 'Value (non-secret)'], [
            ['APP_ENV', $env],
            ['APP_DEBUG', $debug ? 'true' : 'false'],
            ['APP_URL scheme', parse_url($url, PHP_URL_SCHEME) ?: '(none)'],
            ['FORCE_HTTPS', $forceHttps ? 'true' : 'false'],
            ['SESSION_SECURE_COOKIE', $secureCookie ? 'true' : 'false'],
            ['QUEUE_CONNECTION', $queue],
            ['MAIL_MAILER', $mailer ?: '(empty)'],
            ['EMS_PAYMENTS_ENABLED', config('ems.payments.enabled') ? 'true' : 'false'],
            ['ANNOUNCEMENT_EMAIL_HOURLY_CAP', match ($cap = AnnouncementDeliveryService::emailHourlyCap()) {
                false => '(missing or invalid)',
                null => 'unlimited',
                default => (string) $cap,
            }],
            ['SCHEDULER_CRON_INTERVAL_MINUTES', (string) config('app.scheduler_cron_interval_minutes', 1)],
            ['Systems apps', (string) count(config('systems.applications', []))],
        ]);

        foreach ($warnings as $warning) {
            $this->warn('WARN: '.$warning);
        }
        foreach ($failures as $failure) {
            $this->error('FAIL: '.$failure);
        }

        if ($failures === [] && $warnings === []) {
            $this->info('All automated readiness checks passed.');
        } elseif ($failures === []) {
            $this->info('No hard failures. Review warnings and hosting-environment items.');
        }

        $this->newLine();
        $this->line('Manual ops still required: queue workers, cron schedule:run, SMTP/Square secrets, DB+upload backups.');
        $this->line('See docs/PRODUCTION_OPERATIONS_CHECKLIST.md');

        if ($this->option('strict') && $failures !== []) {
            return self::FAILURE;
        }

        return $failures === [] ? self::SUCCESS : self::FAILURE;
    }
}
