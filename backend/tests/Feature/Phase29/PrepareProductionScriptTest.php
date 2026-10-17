<?php

namespace Tests\Feature\Phase29;

use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Runs scripts/prepare-production.sh under bash inside a throwaway directory, with a fake `php`
 * that records every artisan call. Nothing here touches a real database or host.
 */
class PrepareProductionScriptTest extends TestCase
{
    private const SECRET = 'p29-synthetic-db-password';

    private const PENDING = "  Migration name .................................... Batch / Status\n"
        ."  2026_10_14_120000_scrub_job_logs_payload .............................. Pending\n";

    private string $dir;

    private string $appKey;

    private string $envContents;

    protected function setUp(): void
    {
        parent::setUp();

        if (self::bash() === null) {
            $this->markTestSkipped('bash with GNU coreutils not found (set P29_BASH).');
        }

        $this->dir = str_replace('\\', '/', sys_get_temp_dir()).'/p29-script-'.bin2hex(random_bytes(6));
        File::makeDirectory($this->dir.'/scripts', 0755, true);
        File::makeDirectory($this->dir.'/vendor', 0755, true);
        File::makeDirectory($this->dir.'/storage/framework', 0755, true);
        File::makeDirectory($this->dir.'/fakebin', 0755, true);
        File::copy(base_path('scripts/prepare-production.sh'), $this->dir.'/scripts/prepare-production.sh');
        File::put($this->dir.'/vendor/autoload.php', "<?php\n");
        File::put($this->dir.'/fakebin/php', implode("\n", [
            '#!/usr/bin/env bash',
            'printf \'%s\n\' "$*" >> "$FAKE_LOG"',
            'cmd="$2"',
            'if [[ " ${FAKE_FAIL:-} " == *" $cmd "* ]]; then',
            '  echo "SQLSTATE[HY000] [1045] Access denied for user \'msa\'@\'localhost\' (password ${FAKE_SECRET})"',
            '  exit 1',
            'fi',
            'case "$cmd" in',
            '  migrate:status) printf \'%b\' "${FAKE_MIGRATE_STATUS:-}" ;;',
            '  msa:production-check) echo "All automated readiness checks passed." ;;',
            'esac',
            'exit 0',
            '',
        ]));
        chmod($this->dir.'/fakebin/php', 0755);

        $this->appKey = 'base64:'.base64_encode(random_bytes(32));
        $this->writeEnv([]);
    }

    protected function tearDown(): void
    {
        if (isset($this->dir) && is_dir($this->dir)) {
            File::deleteDirectory($this->dir);
        }

        parent::tearDown();
    }

    private static function bash(): ?string
    {
        $candidates = array_filter([
            getenv('P29_BASH') ?: null,
            PHP_OS_FAMILY === 'Windows' ? 'C:\\Program Files\\Git\\bin\\bash.exe' : '/bin/bash',
            '/usr/bin/bash',
        ]);
        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @param  array<string, string|null>  $overrides  null removes the line
     */
    private function writeEnv(array $overrides): void
    {
        $values = array_merge([
            'APP_NAME' => 'MSA',
            'APP_ENV' => 'production',
            'APP_KEY' => $this->appKey,
            'APP_DEBUG' => 'false',
            'APP_URL' => 'https://api.example.test',
            'DB_CONNECTION' => 'mysql',
            'DB_DATABASE' => 'msa_production',
            'DB_PASSWORD' => self::SECRET,
            'QUEUE_CONNECTION' => 'database',
        ], $overrides);

        $lines = [];
        foreach ($values as $key => $value) {
            if ($value !== null) {
                $lines[] = "{$key}={$value}";
            }
        }
        $this->envContents = implode("\n", $lines)."\n";
        File::put($this->dir.'/.env', $this->envContents);
    }

    /**
     * @param  array<string, string>  $evidence
     */
    private function writeEvidence(array $evidence = []): string
    {
        $values = array_merge([
            'backup_sha256' => hash('sha256', 'synthetic dump'),
            'backup_location' => 'offline drive #2',
            'restore_verified' => 'yes',
            'restore_database' => 'msa_restore_check',
            'restore_checked_at' => gmdate('Y-m-d\TH:i:s\Z', time() - 3600),
            'restore_table_count' => '167',
            'app_key_sha256' => hash('sha256', $this->appKey),
            'storage_backup_sha256' => hash('sha256', 'synthetic storage'),
            'operator' => 'ops-tester',
        ], $evidence);

        $path = $this->dir.'/evidence.txt';
        File::put($path, implode("\n", array_map(fn ($k, $v) => "{$k}={$v}", array_keys($values), $values))."\n");

        return $path;
    }

    /**
     * @param  array<string, string>  $env
     * @return array{exit: int, output: string, calls: list<string>}
     */
    private function runScript(array $args, array $env = []): array
    {
        $fakeLog = $this->dir.'/fake-php.log';
        File::put($fakeLog, '');

        $environment = array_merge(getenv(), [
            'PHP_BIN' => $this->dir.'/fakebin/php',
            'FAKE_LOG' => $fakeLog,
            'FAKE_SECRET' => self::SECRET,
            'FAKE_MIGRATE_STATUS' => self::PENDING,
        ], $env);

        $process = proc_open(
            array_merge([self::bash(), $this->dir.'/scripts/prepare-production.sh'], $args),
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $this->dir,
            $environment
        );
        $output = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);

        $this->assertSame($this->envContents, File::get($this->dir.'/.env'), '.env must never be modified');
        $this->assertStringNotContainsString(self::SECRET, $output, 'secrets must not reach the terminal');
        $this->assertStringNotContainsString(substr($this->appKey, 7), $output, 'APP_KEY must not be printed');

        $calls = array_values(array_filter(explode("\n", str_replace("\r", '', File::get($fakeLog)))));
        foreach ($calls as $call) {
            $this->assertDoesNotMatchRegularExpression(
                '/key:generate|migrate:(fresh|refresh|rollback|reset)|db:wipe|cache:clear|optimize:clear/',
                $call
            );
        }

        return ['exit' => $exit, 'output' => $output, 'calls' => $calls];
    }

    private function migrateCalls(array $calls): int
    {
        return count(array_filter($calls, fn ($c) => str_starts_with($c, 'artisan migrate --force')));
    }

    /**
     * @return array<string, array{array<string, string|null>}>
     */
    public static function missingOrMalformedKeys(): array
    {
        return [
            'blank' => [['APP_KEY' => '']],
            'absent' => [['APP_KEY' => null]],
            'quoted blank' => [['APP_KEY' => '""']],
            'short base64' => [['APP_KEY' => 'base64:'.base64_encode('too-short')]],
            'wrong length raw' => [['APP_KEY' => 'abc']],
        ];
    }

    #[DataProvider('missingOrMalformedKeys')]
    public function test_missing_or_malformed_app_key_is_a_hard_stop_and_never_generated(array $override): void
    {
        $this->writeEnv($override);

        foreach (['preflight', 'migrate', 'caches', 'verify'] as $phase) {
            $result = $this->runScript([$phase], ['BACKUP_EVIDENCE' => $this->writeEvidence()]);

            $this->assertSame(4, $result['exit'], $phase.': '.$result['output']);
            $this->assertStringContainsString('APP_KEY', $result['output']);
            $this->assertSame([], $result['calls'], "{$phase} must stop before calling artisan");
        }
    }

    /**
     * @return array<string, array{array<string, string|null>, string}>
     */
    public static function unsafeEnvironments(): array
    {
        return [
            'debug on' => [['APP_DEBUG' => 'true'], 'APP_DEBUG'],
            'not production' => [['APP_ENV' => 'local'], 'APP_ENV'],
            'http url' => [['APP_URL' => 'http://api.example.test'], 'APP_URL'],
            'sync queue' => [['QUEUE_CONNECTION' => 'sync'], 'QUEUE_CONNECTION'],
            'queue unset' => [['QUEUE_CONNECTION' => null], 'QUEUE_CONNECTION'],
        ];
    }

    #[DataProvider('unsafeEnvironments')]
    public function test_unsafe_environment_stops_before_anything_runs(array $override, string $mentioned): void
    {
        $this->writeEnv($override);

        $result = $this->runScript(['preflight']);

        $this->assertSame(3, $result['exit'], $result['output']);
        $this->assertStringContainsString($mentioned, $result['output']);
        $this->assertSame([], $result['calls']);
    }

    public function test_missing_env_is_never_created_from_a_template(): void
    {
        File::delete($this->dir.'/.env');
        File::put($this->dir.'/.env.production', "APP_KEY=\nAPP_ENV=production\n");
        $this->envContents = '';

        $fakeLog = $this->dir.'/fake-php.log';
        File::put($fakeLog, '');
        $process = proc_open([self::bash(), $this->dir.'/scripts/prepare-production.sh', 'preflight'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $this->dir,
            array_merge(getenv(), ['PHP_BIN' => $this->dir.'/fakebin/php', 'FAKE_LOG' => $fakeLog]));
        $output = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $this->assertSame(3, proc_close($process), $output);
        $this->assertFileDoesNotExist($this->dir.'/.env');
        $this->assertSame('', File::get($fakeLog));
    }

    public function test_missing_vendor_directory_stops_preflight(): void
    {
        File::deleteDirectory($this->dir.'/vendor');

        $result = $this->runScript(['preflight']);

        $this->assertSame(3, $result['exit']);
        $this->assertStringContainsString('vendor/', $result['output']);
    }

    public function test_unreachable_database_fails_without_printing_credentials(): void
    {
        $result = $this->runScript(['preflight'], ['FAKE_FAIL' => 'migrate:status']);

        $this->assertSame(5, $result['exit'], $result['output']);
        $this->assertStringContainsString('Cannot reach the database', $result['output']);
        $logs = glob($this->dir.'/storage/logs/release-*.log');
        $this->assertNotEmpty($logs);
        $this->assertStringNotContainsString(self::SECRET, File::get($logs[0]));
        $this->assertStringContainsString('[REDACTED]', File::get($logs[0]));
        if (PHP_OS_FAMILY !== 'Windows') {
            $this->assertSame('0600', substr(sprintf('%o', fileperms($logs[0])), -4), 'release log may hold connection errors');
        }
    }

    public function test_failed_readiness_check_cannot_be_ignored(): void
    {
        $result = $this->runScript(['preflight'], ['FAKE_FAIL' => 'msa:production-check']);

        $this->assertSame(8, $result['exit'], $result['output']);
        $this->assertStringNotContainsString('PASSED', $result['output']);
    }

    public function test_preflight_is_read_only_and_reports_pending_migrations(): void
    {
        $result = $this->runScript(['preflight']);

        $this->assertSame(0, $result['exit'], $result['output']);
        $this->assertStringContainsString('Pending migrations: 1', $result['output']);
        $this->assertStringContainsString('2026_10_14_120000_scrub_job_logs_payload', $result['output']);
        $this->assertStringContainsString('fingerprint sha256:'.substr(hash('sha256', $this->appKey), 0, 12), $result['output']);
        $this->assertSame(0, $this->migrateCalls($result['calls']));
        foreach ($result['calls'] as $call) {
            $this->assertMatchesRegularExpression('/^artisan (migrate:status|msa:production-check --strict) --no-ansi$/', $call);
        }
    }

    /**
     * @return array<string, array{array<string, string>}>
     */
    public static function invalidEvidence(): array
    {
        return [
            'restore not verified' => [['restore_verified' => 'no']],
            'stale restore' => [['restore_checked_at' => gmdate('Y-m-d\TH:i:s\Z', time() - 25 * 3600)]],
            'future restore' => [['restore_checked_at' => gmdate('Y-m-d\TH:i:s\Z', time() + 3600)]],
            'unparseable time' => [['restore_checked_at' => 'yesterday-ish']],
            'different app key' => [['app_key_sha256' => hash('sha256', 'base64:another-key')]],
            'restored over production' => [['restore_database' => 'msa_production']],
            'bad checksum' => [['backup_sha256' => 'not-a-digest']],
            'no storage backup' => [['storage_backup_sha256' => '']],
            'zero tables' => [['restore_table_count' => '0']],
            'no operator' => [['operator' => '']],
        ];
    }

    #[DataProvider('invalidEvidence')]
    public function test_migrate_refuses_without_valid_restore_evidence(array $evidence): void
    {
        File::put($this->dir.'/storage/framework/down', '{}');

        $result = $this->runScript(['migrate'], ['BACKUP_EVIDENCE' => $this->writeEvidence($evidence)]);

        $this->assertSame(6, $result['exit'], $result['output']);
        $this->assertSame(0, $this->migrateCalls($result['calls']));
    }

    public function test_migrate_refuses_without_evidence_file_or_matching_backup_file(): void
    {
        File::put($this->dir.'/storage/framework/down', '{}');

        $this->assertSame(6, $this->runScript(['migrate'])['exit']);
        $this->assertSame(6, $this->runScript(['migrate'], ['BACKUP_EVIDENCE' => $this->dir.'/nope.txt'])['exit']);

        File::put($this->dir.'/dump.sql', 'different contents');
        $result = $this->runScript(['migrate'], ['BACKUP_EVIDENCE' => $this->writeEvidence(), 'BACKUP_FILE' => $this->dir.'/dump.sql']);
        $this->assertSame(6, $result['exit'], $result['output']);
        $this->assertSame(0, $this->migrateCalls($result['calls']));
    }

    public function test_migrate_requires_maintenance_mode(): void
    {
        $result = $this->runScript(['migrate'], ['BACKUP_EVIDENCE' => $this->writeEvidence()]);

        $this->assertSame(7, $result['exit'], $result['output']);
        $this->assertSame(0, $this->migrateCalls($result['calls']));
    }

    public function test_migrate_runs_once_with_valid_gates_and_leaves_maintenance_on(): void
    {
        File::put($this->dir.'/storage/framework/down', '{}');
        File::put($this->dir.'/dump.sql', 'synthetic dump');

        $result = $this->runScript(['migrate'], [
            'BACKUP_EVIDENCE' => $this->writeEvidence(),
            'BACKUP_FILE' => $this->dir.'/dump.sql',
        ]);

        $this->assertSame(0, $result['exit'], $result['output']);
        $this->assertSame(1, $this->migrateCalls($result['calls']));
        $this->assertContains('artisan migrate --force --no-interaction --no-ansi', $result['calls']);
        $this->assertStringContainsString('Maintenance mode is still ON', $result['output']);
        $this->assertFileExists($this->dir.'/storage/framework/down');
    }

    public function test_failed_migration_reports_status_and_safe_recovery(): void
    {
        File::put($this->dir.'/storage/framework/down', '{}');

        $result = $this->runScript(['migrate'], ['BACKUP_EVIDENCE' => $this->writeEvidence(), 'FAKE_FAIL' => 'migrate']);

        $this->assertSame(9, $result['exit'], $result['output']);
        $this->assertStringContainsString('Status: FAILED (exit 9)', $result['output']);
        $this->assertStringContainsString('still in maintenance mode', $result['output']);
        $this->assertStringContainsString('Do NOT run migrate:rollback', $result['output']);
        $this->assertFileExists($this->dir.'/storage/framework/down');
    }

    public function test_caches_rebuild_without_clearing_the_application_cache(): void
    {
        $result = $this->runScript(['caches']);

        $this->assertSame(0, $result['exit'], $result['output']);
        $this->assertSame([
            'artisan config:clear --no-ansi', 'artisan event:clear --no-ansi', 'artisan route:clear --no-ansi', 'artisan view:clear --no-ansi',
            'artisan config:cache --no-ansi', 'artisan event:cache --no-ansi', 'artisan route:cache --no-ansi', 'artisan view:cache --no-ansi',
        ], $result['calls']);

        $failed = $this->runScript(['caches'], ['FAKE_FAIL' => 'route:cache']);
        $this->assertSame(10, $failed['exit']);
        $this->assertStringContainsString('route:cache', $failed['output']);
    }

    public function test_verify_requires_no_pending_migrations_then_signals_workers(): void
    {
        $this->assertSame(9, $this->runScript(['verify'])['exit']);

        $result = $this->runScript(['verify'], ['FAKE_MIGRATE_STATUS' => '']);
        $this->assertSame(0, $result['exit'], $result['output']);
        $this->assertContains('artisan queue:restart --no-ansi', $result['calls']);
    }

    private function publishedBuild(): string
    {
        $dist = $this->dir.'/public_html';
        File::makeDirectory($dist.'/assets', 0755, true);
        File::put($dist.'/index.html', '<script type="module" src="/assets/index-abc.js"></script><link rel="stylesheet" href="/assets/index-def.css">');
        File::put($dist.'/assets/index-abc.js', 'const api="https://api.example.test/api/v1";');
        File::put($dist.'/assets/index-def.css', 'body{}');
        File::put($dist.'/.htaccess', "RewriteEngine On\nRewriteRule . /index.html [L]\nHeader set X-Content-Type-Options \"nosniff\"\n");

        return $dist;
    }

    public function test_verify_assets_accepts_a_complete_build(): void
    {
        $dist = $this->publishedBuild();

        $result = $this->runScript(['verify-assets', $dist], ['EXPECTED_API_URL' => 'https://api.example.test/api/v1']);

        $this->assertSame(0, $result['exit'], $result['output']);
        $this->assertSame([], $result['calls']);
    }

    /**
     * @return array<string, array{callable(string): void, array<string, string>}>
     */
    public static function brokenBuilds(): array
    {
        return [
            'missing asset' => [fn (string $d) => unlink($d.'/assets/index-def.css'), []],
            'stale htaccess' => [fn (string $d) => file_put_contents($d.'/.htaccess', "RewriteRule . /index.html [L]\n"), []],
            'source map' => [fn (string $d) => file_put_contents($d.'/assets/index-abc.js.map', '{}'), []],
            'env file' => [fn (string $d) => file_put_contents($d.'/.env', 'X=1'), []],
            'sql dump' => [fn (string $d) => file_put_contents($d.'/backup.sql', '--'), []],
            'dev api url' => [fn (string $d) => file_put_contents($d.'/assets/index-abc.js', 'const api="http://localhost:8000/api/v1";'), []],
            'wrong api url' => [fn (string $d) => null, ['EXPECTED_API_URL' => 'https://api.sfumsa.ca/api/v1']],
        ];
    }

    #[DataProvider('brokenBuilds')]
    public function test_verify_assets_rejects_broken_or_leaky_builds(callable $break, array $env): void
    {
        $dist = $this->publishedBuild();
        $break($dist);

        $result = $this->runScript(['verify-assets', $dist], $env);

        $this->assertSame(11, $result['exit'], $result['output']);
    }

    public function test_unknown_phase_and_rollback_help_run_nothing(): void
    {
        $this->assertSame(2, $this->runScript([])['exit']);
        $this->assertSame(2, $this->runScript(['deploy-everything'])['exit']);

        $help = $this->runScript(['rollback-help']);
        $this->assertSame(0, $help['exit']);
        $this->assertStringContainsString('Do not run migrate:rollback', $help['output']);
        $this->assertStringContainsString('NEW database', $help['output']);
        $this->assertSame([], $help['calls']);
    }

    public function test_scripts_contain_no_executable_destructive_or_key_commands(): void
    {
        foreach (['scripts/prepare-production.sh', 'scripts/prepare-production.ps1'] as $script) {
            $code = collect(preg_split('/\R/', File::get(base_path($script))))
                ->reject(fn (string $line) => preg_match('/^\s*#/', $line) === 1)
                ->implode("\n");

            $this->assertDoesNotMatchRegularExpression(
                '/^(?!\s*echo\b)[^"\'\n]*\bartisan\s+(key:generate|migrate:(fresh|refresh|rollback|reset)|db:wipe|optimize:clear|cache:clear)\b/m',
                $code,
                $script
            );
            $this->assertDoesNotMatchRegularExpression('/^\s*(for\s+\w+\s+in\b.*\b(cache:clear|optimize:clear|key:generate)\b|composer\s+install)/m', $code, $script);
            $this->assertDoesNotMatchRegularExpression('/(production-check|migrate --force)[^\n]*\|\|\s*(true|:)/', $code, "{$script} must not swallow failures");
        }
    }

    public function test_powershell_script_refuses_to_run(): void
    {
        $shell = PHP_OS_FAMILY === 'Windows' ? 'powershell' : trim((string) shell_exec('command -v pwsh'));
        if ($shell === '') {
            $this->markTestSkipped('PowerShell not available.');
        }

        exec(escapeshellarg($shell).' -NoProfile -NonInteractive -ExecutionPolicy Bypass -File '.escapeshellarg(base_path('scripts/prepare-production.ps1')).' 2>&1', $output, $exit);

        $this->assertSame(1, $exit, implode("\n", $output));
        $this->assertStringContainsString('blocked', implode("\n", $output));
    }
}
