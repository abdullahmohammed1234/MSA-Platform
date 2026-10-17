<?php

namespace Tests\Feature\Phase30;

use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReleaseArtifactValidationTest extends TestCase
{
    use RunsReleaseScripts;

    private const API_URL = 'https://api.sfumsa.ca/api/v1';

    private string $artifact;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeWorkDir('p30-validate');
        $this->artifact = $this->workDir.'/artifact';
        $this->writeValidArtifact($this->artifact, self::API_URL);
    }

    protected function tearDown(): void
    {
        $this->removeWorkDir();
        parent::tearDown();
    }

    private function validate(array $env = []): array
    {
        return $this->runBash([self::releaseScript('validate-release.sh'), $this->artifact], ['EXPECTED_API_URL' => self::API_URL] + $env);
    }

    private function writeChecksums(): void
    {
        $lines = [];
        foreach (File::allFiles($this->artifact, true) as $file) {
            $relative = './'.str_replace('\\', '/', $file->getRelativePathname());
            if ($relative !== './SHA256SUMS') {
                $lines[$relative] = hash_file('sha256', $file->getPathname()).'  '.$relative;
            }
        }
        ksort($lines, SORT_STRING);
        File::put($this->artifact.'/SHA256SUMS', implode("\n", $lines)."\n");
    }

    public function test_a_clean_artifact_built_with_the_real_htaccess_files_is_valid(): void
    {
        $result = $this->validate();

        $this->assertSame(0, $result['exit'], $result['output']);
        $this->assertStringContainsString('Release artifact: VALID', $result['output']);
    }

    public function test_matching_checksums_pass_and_any_tampering_fails(): void
    {
        $this->writeChecksums();
        $this->assertSame(0, $this->validate()['exit']);

        File::append($this->artifact.'/public_html/assets/index-abc123.js', '/*tampered*/');
        $result = $this->validate();
        $this->assertSame(26, $result['exit'], $result['output']);

        $this->writeChecksums();
        File::put($this->artifact.'/public_html/extra.txt', 'unlisted');
        $result = $this->validate();
        $this->assertSame(26, $result['exit'], $result['output']);
    }

    /** @return array<string, array{\Closure(string): void, int, string}> */
    public static function brokenArtifacts(): array
    {
        $put = fn (string $path, string $contents = 'x') => function (string $a) use ($path, $contents): void {
            File::ensureDirectoryExists(dirname($a.'/'.$path));
            File::put($a.'/'.$path, $contents);
        };
        $edit = fn (string $path, string $search, string $replace) => function (string $a) use ($path, $search, $replace): void {
            File::put($a.'/'.$path, str_replace($search, $replace, File::get($a.'/'.$path)));
        };

        return [
            'staging env file' => [$put('msa-backend/.env.staging', 'DB_PASSWORD=synthetic'), 21, '.env.staging'],
            'production env file' => [$put('msa-backend/.env', 'APP_KEY='), 21, 'msa-backend/.env'],
            'env file in public_html' => [$put('public_html/.env.production', 'VITE_API_URL=x'), 21, '.env.production'],
            'database dump' => [$put('msa-backend/backup-2026-10-15.sql'), 21, '.sql'],
            'log file' => [$put('msa-backend/storage/logs/laravel.log'), 21, 'laravel.log'],
            'backup copy' => [$put('public_html/index.html.bak'), 21, '.bak'],
            'private key' => [$put('msa-backend/storage/oauth-private.key'), 21, '.key'],
            'ssh key' => [$put('msa-backend/id_rsa'), 21, 'id_rsa'],
            'composer auth' => [$put('msa-backend/auth.json', '{}'), 21, 'auth.json'],
            'archive' => [$put('public_html/site.zip'), 21, 'site.zip'],
            'git metadata' => [$put('msa-backend/.git/config'), 21, '.git'],
            'test suite' => [$put('msa-backend/tests/Feature/ExampleTest.php', '<?php'), 21, 'tests'],
            'sqlite database under an innocent name' => [$put('msa-backend/sfumsa_platform_test', 'SQLite format 3'."\0".str_repeat('x', 2048)), 21, 'SQLite database'],
            'session file' => [$put('msa-backend/storage/framework/sessions/abc', 'payload'), 21, 'storage/'],
            'cached config' => [$put('msa-backend/bootstrap/cache/config.php', '<?php return [];'), 21, 'config.php'],
            'private key material in config' => [$put('msa-backend/config/keys.php', "-----BEGIN RSA PRIVATE KEY-----\nMIIB\n"), 22, 'config/keys.php'],
            'app key in application code' => [$put('msa-backend/config/fallback.php', "<?php return 'base64:".base64_encode(str_repeat('k', 32))."';"), 22, 'config/fallback.php'],
            'app key compiled into vendor/composer' => [$put('msa-backend/vendor/composer/autoload_static.php', "<?php // base64:".base64_encode(str_repeat('z', 32))), 22, 'autoload_static.php'],
            'live payment token in the frontend' => [$put('public_html/assets/chunk.js', 'const t="sk_live_'.str_repeat('a', 24).'"'), 22, 'chunk.js'],
            'dev dependency installed' => [$put('msa-backend/vendor/phpunit/phpunit/README.md'), 23, 'phpunit/phpunit'],
            'dev install recorded' => [$put('msa-backend/vendor/composer/installed.json', '{"packages": [], "dev": true}'), 23, 'dev install'],
            'missing nosniff' => [$edit('public_html/.htaccess', 'X-Content-Type-Options', 'X-Removed'), 24, 'X-Content-Type-Options'],
            'missing frame protection' => [$edit('public_html/.htaccess', 'X-Frame-Options', 'X-Removed'), 24, 'X-Frame-Options'],
            'csp enforced instead of report-only' => [$edit('public_html/.htaccess', 'Content-Security-Policy-Report-Only', 'Content-Security-Policy'), 24, 'CSP'],
            'dotfiles not refused' => [$edit('public_html/.htaccess', 'RewriteRule (^|/)\.(?!well-known/) - [F,L]', '# removed'), 24, 'dotfiles'],
            'well-known blocked too' => [$edit('public_html/.htaccess', '(?!well-known/)', ''), 24, 'well-known'],
            'leftovers not refused' => [$edit('public_html/.htaccess', 'RewriteRule \.(map|sql|bak|log|zip|gz|env)$ - [F,L,NC]', '# removed'), 24, 'source maps'],
            'no spa fallback' => [$edit('public_html/.htaccess', 'RewriteRule . /index.html [L]', '# removed'), 24, 'SPA fallback'],
            'backend front controller missing' => [$edit('msa-backend/public/.htaccess', 'RewriteRule ^ index.php [L]', '# removed'), 24, 'front controller'],
            'missing asset' => [fn (string $a) => File::delete($a.'/public_html/assets/index-abc123.css'), 25, 'index-abc123.css'],
            'source map published' => [$put('public_html/assets/index-abc123.js.map', '{}'), 25, '.map'],
            'development api url' => [$edit('public_html/assets/index-abc123.js', 'https://api.sfumsa.ca/api/v1', 'http://localhost:8000/api/v1'), 25, 'development API URL'],
            'wrong production api url' => [$edit('public_html/assets/index-abc123.js', 'https://api.sfumsa.ca/api/v1', 'https://api.example.test/api/v1'), 25, 'EXPECTED_API_URL'],
            'storage symlink shipped' => [$put('msa-backend/public/storage/file.txt'), 20, 'storage:link'],
            'vendor missing' => [fn (string $a) => File::delete($a.'/msa-backend/vendor/autoload.php'), 20, 'vendor/autoload.php'],
            'writable directory missing' => [fn (string $a) => File::deleteDirectory($a.'/msa-backend/storage/framework/sessions'), 20, 'storage/framework/sessions'],
            'frontend htaccess missing' => [fn (string $a) => File::delete($a.'/public_html/.htaccess'), 20, 'public_html/.htaccess'],
        ];
    }

    #[DataProvider('brokenArtifacts')]
    public function test_unsafe_or_incomplete_artifacts_are_refused(\Closure $break, int $expectedExit, string $mention): void
    {
        $break($this->artifact);

        $result = $this->validate();

        $this->assertSame($expectedExit, $result['exit'], $result['output']);
        $this->assertStringContainsString($mention, $result['output']);
        $this->assertStringContainsString('must not be published', $result['output']);
        $this->assertStringNotContainsString('VALID', $result['output']);
    }

    public function test_validation_is_read_only(): void
    {
        File::put($this->artifact.'/msa-backend/.env.staging', 'DB_PASSWORD=synthetic');
        $before = $this->snapshot();

        $this->validate();

        $this->assertSame($before, $this->snapshot());
    }

    /** @return array<string, string> */
    private function snapshot(): array
    {
        $files = [];
        foreach (File::allFiles($this->artifact, true) as $file) {
            $files[str_replace('\\', '/', $file->getRelativePathname())] = hash_file('sha256', $file->getPathname());
        }
        ksort($files);

        return $files;
    }
}
