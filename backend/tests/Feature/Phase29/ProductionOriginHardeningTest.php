<?php

namespace Tests\Feature\Phase29;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProductionOriginHardeningTest extends TestCase
{
    use RefreshDatabase;

    private const ENV_KEYS = ['APP_ENV', 'FRONTEND_URL', 'CONNECT_URL', 'PROJECTS_URL', 'CORS_ALLOWED_ORIGINS'];

    /** @var array<string, array{0: mixed, 1: mixed, 2: string|false}> */
    private array $savedEnv = [];

    protected function tearDown(): void
    {
        foreach ($this->savedEnv as $key => [$server, $env, $put]) {
            $this->restore($key, $server, $env, $put);
        }
        $this->savedEnv = [];

        parent::tearDown();
    }

    /** @param array<string, string> $values */
    private function corsOriginsWith(array $values): array
    {
        foreach (self::ENV_KEYS as $key) {
            $this->savedEnv[$key] ??= [$_SERVER[$key] ?? null, $_ENV[$key] ?? null, getenv($key)];
            $value = $values[$key] ?? null;
            $this->restore($key, $value, $value, $value ?? false);
        }

        return (require config_path('cors.php'))['allowed_origins'];
    }

    private function restore(string $key, mixed $server, mixed $env, string|false $put): void
    {
        if ($server === null) {
            unset($_SERVER[$key]);
        } else {
            $_SERVER[$key] = $server;
        }
        if ($env === null) {
            unset($_ENV[$key]);
        } else {
            $_ENV[$key] = $env;
        }
        putenv($put === false ? $key : "{$key}={$put}");
    }

    public function test_production_cors_drops_loopback_origins_but_keeps_configured_ones(): void
    {
        $origins = $this->corsOriginsWith([
            'APP_ENV' => 'production',
            'FRONTEND_URL' => 'https://sfumsa.example',
            'CONNECT_URL' => 'https://connect.sfumsa.example',
            'PROJECTS_URL' => 'https://projects.sfumsa.example',
            'CORS_ALLOWED_ORIGINS' => 'https://portal.sfumsa.example, http://127.0.0.1:9000',
        ]);

        $this->assertSame([
            'https://sfumsa.example',
            'https://connect.sfumsa.example',
            'https://projects.sfumsa.example',
            'https://sfumsa.ca',
            'https://www.sfumsa.ca',
            'https://connect.sfumsa.ca',
            'https://projects.sfumsa.ca',
            'https://portal.sfumsa.example',
        ], $origins);
        $this->assertNotContains('http://127.0.0.1:9000', $origins);
    }

    public function test_production_cors_with_unset_urls_trusts_no_dev_server(): void
    {
        $origins = $this->corsOriginsWith(['APP_ENV' => 'production']);

        $this->assertSame([
            'https://sfumsa.ca',
            'https://connect.sfumsa.ca',
            'https://projects.sfumsa.ca',
            'https://www.sfumsa.ca',
        ], $origins);
        foreach ($origins as $origin) {
            $host = parse_url($origin, PHP_URL_HOST);
            $this->assertNotContains($host, ['localhost', '127.0.0.1', '[::1]', '::1']);
        }
    }

    public function test_non_production_cors_keeps_the_local_dev_origins(): void
    {
        $origins = $this->corsOriginsWith(['APP_ENV' => 'local']);

        $this->assertContains('http://localhost:5173', $origins);
        $this->assertContains('http://127.0.0.1:5174', $origins);
        $this->assertContains('http://localhost:5175', $origins);
        $this->assertContains('https://projects.sfumsa.ca', $origins);
    }

    /** @return array<string, array{string, bool}> */
    public static function frontendUrls(): array
    {
        return [
            'public https origin' => ['https://sfumsa.example', true],
            'plain http' => ['http://sfumsa.example', false],
            'localhost dev server' => ['http://localhost:5173', false],
            'https loopback' => ['https://127.0.0.1', false],
            'blank' => ['', false],
        ];
    }

    #[DataProvider('frontendUrls')]
    public function test_production_check_requires_a_public_https_frontend_url(string $frontendUrl, bool $accepted): void
    {
        config([
            'app.env' => 'production', 'app.debug' => false, 'app.url' => 'https://api.example.test',
            'app.frontend_url' => $frontendUrl,
            'app.projects_url' => 'https://projects.sfumsa.ca',
            'queue.default' => 'database', 'announcements.delivery.email_hourly_cap' => '100',
        ]);

        $exit = Artisan::call('msa:production-check');
        $output = Artisan::output();

        $this->assertSame($accepted ? 0 : 1, $exit, $output);
        $this->assertSame(! $accepted, str_contains($output, 'FAIL: FRONTEND_URL'), $output);
    }

    /** @return array<string, array{string, bool}> */
    public static function projectsUrls(): array
    {
        return [
            'public https origin' => ['https://projects.sfumsa.ca', true],
            'custom https origin' => ['https://projects.sfumsa.example', true],
            'plain http' => ['http://projects.sfumsa.ca', false],
            'localhost' => ['http://localhost:5175', false],
            'blank' => ['', false],
        ];
    }

    #[DataProvider('projectsUrls')]
    public function test_production_check_requires_a_public_https_projects_url(string $projectsUrl, bool $accepted): void
    {
        config([
            'app.env' => 'production', 'app.debug' => false, 'app.url' => 'https://api.example.test',
            'app.frontend_url' => 'https://sfumsa.example',
            'app.projects_url' => $projectsUrl,
            'queue.default' => 'database', 'announcements.delivery.email_hourly_cap' => '100',
        ]);

        $exit = Artisan::call('msa:production-check');
        $output = Artisan::output();

        $this->assertSame($accepted ? 0 : 1, $exit, $output);
        $this->assertSame(! $accepted, str_contains($output, 'FAIL: PROJECTS_URL'), $output);
    }
}
