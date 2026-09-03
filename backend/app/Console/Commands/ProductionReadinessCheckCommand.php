<?php

namespace App\Console\Commands;

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
        if ($apps !== ['cms', 'dams', 'dawah-academy', 'ems', 'main-website', 'store']) {
            $failures[] = 'Systems registry must contain the registered platform applications';
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
