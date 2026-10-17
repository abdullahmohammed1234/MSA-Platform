<?php

namespace Tests\Feature\Phase30;

use Tests\TestCase;

/**
 * Guards against environment files entering git or the release artifact. Only key names are
 * inspected; no value is ever printed or asserted on.
 */
class EnvironmentFileExposureTest extends TestCase
{
    /** Example templates and frontend files that hold public VITE_ build settings only. */
    private const ALLOWED_TRACKED = [
        '.env.example',
        'backend/.env.example',
        'frontend/.env',
        'frontend/.env.example',
        'frontend/.env.production',
        'frontend/.env.staging',
        'connect/.env.example',
        'connect/.env.production',
        'connect/.env.staging',
    ];

    /**
     * Tracked since the initial commit and public on GitHub. Untracking it needs owner approval
     * (Phase 30 blocker B2); the release builder strips it and the validator refuses it meanwhile.
     */
    private const KNOWN_EXPOSURE = ['backend/.env.staging'];

    private static function git(string ...$args): array
    {
        $process = proc_open(array_merge(['git'], $args), [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path('..'));
        $out = stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);

        return [$exit, array_values(array_filter(explode("\n", str_replace("\r", '', (string) $out))))];
    }

    protected function setUp(): void
    {
        parent::setUp();
        [$exit] = self::git('rev-parse', '--is-inside-work-tree');
        if ($exit !== 0) {
            $this->markTestSkipped('Not running inside the git work tree.');
        }
    }

    public function test_no_new_environment_file_can_be_tracked(): void
    {
        [, $tracked] = self::git('ls-files', '--', '*.env', '*.env.*', '.env', '.env.*', '**/.env', '**/.env.*');
        $tracked = array_values(array_unique($tracked));

        $unexpected = array_diff($tracked, self::ALLOWED_TRACKED, self::KNOWN_EXPOSURE);

        $this->assertSame([], array_values($unexpected), 'environment files must not be committed');
    }

    public function test_tracked_frontend_env_files_hold_only_public_vite_settings(): void
    {
        foreach (self::ALLOWED_TRACKED as $path) {
            $full = base_path('../'.$path);
            if (! str_contains($path, '/') || str_starts_with($path, 'backend/') || ! is_file($full)) {
                continue;
            }
            foreach (file($full, FILE_IGNORE_NEW_LINES) as $number => $line) {
                if (! preg_match('/^\s*([A-Za-z_][A-Za-z0-9_]*)\s*=(.*)$/', $line, $m)) {
                    continue;
                }
                $this->assertStringStartsWith('VITE_', $m[1], "{$path}:".($number + 1).' must only define public VITE_ keys');
                $this->assertDoesNotMatchRegularExpression('/(SECRET|PASSWORD|PRIVATE|TOKEN)/i', $m[1], "{$path}:".($number + 1));
                $this->assertDoesNotMatchRegularExpression('/sk_live_|EAAA[A-Za-z0-9_-]{40,}|-----BEGIN|base64:[A-Za-z0-9+\/]{43}=/', $m[2], "{$path}:".($number + 1).' holds a credential-shaped value');
            }
        }
    }

    public function test_backend_env_variants_and_local_databases_are_ignored(): void
    {
        foreach (['backend/.env', 'backend/.env.staging', 'backend/.env.local', 'backend/.env.production', 'backend/.env.backup', 'backend/sfumsa_platform_test', 'backend/database/database.sqlite', 'backend/local.sqlite'] as $path) {
            [$exit] = self::git('check-ignore', '-q', '--no-index', $path);
            $this->assertSame(0, $exit, "{$path} must be ignored by git");
        }
        [$exit] = self::git('check-ignore', '-q', '--no-index', 'backend/.env.example');
        $this->assertSame(1, $exit, 'backend/.env.example must stay trackable');
    }

    public function test_release_artifact_tooling_strips_and_refuses_environment_files(): void
    {
        $builder = file_get_contents(base_path('../scripts/release/build-release.sh'));
        $validator = file_get_contents(base_path('../scripts/release/validate-release.sh'));

        $this->assertStringContainsString("find . \\( -name '.env' -o -name '.env.*' \\) ! -name '.env.example' -type f", $builder);
        $this->assertStringContainsString('git -C "$REPO_ROOT" archive --format=tar "$COMMIT" backend frontend', $builder);
        $this->assertStringContainsString("\\( -name '.env' -o -name '.env.*' \\) ! -name '.env.example'", $validator);
    }
}
