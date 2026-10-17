<?php

namespace Tests\Feature\Phase30;

use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Exercises scripts/release/build-release.sh against a throwaway git repository with fake
 * php, composer and npm. No real dependency install, build or deployment happens.
 */
class BuildReleaseScriptTest extends TestCase
{
    use RunsReleaseScripts;

    private const SECRET = 'p30-synthetic-staging-password';

    private const API_URL = 'https://api.sfumsa.ca/api/v1';

    private string $repo;

    private string $commit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeWorkDir('p30-build');
        $this->repo = $this->workDir.'/repo';
        $this->writeFixtureRepository();
        $this->writeFakeTools();
    }

    protected function tearDown(): void
    {
        $this->removeWorkDir();
        parent::tearDown();
    }

    private function writeFixtureRepository(): void
    {
        $files = [
            'backend/artisan' => "#!/usr/bin/env php\n<?php\n",
            'backend/composer.json' => json_encode(['require' => ['php' => '^8.2', 'laravel/framework' => '^12.0'], 'require-dev' => ['phpunit/phpunit' => '^11.0']], JSON_PRETTY_PRINT),
            'backend/composer.lock' => "{\"packages\": []}\n",
            'backend/public/index.php' => "<?php\n",
            'backend/bootstrap/app.php' => "<?php\n",
            'backend/bootstrap/cache/.gitignore' => "*\n!.gitignore\n",
            'backend/config/app.php' => "<?php return ['name' => 'MSA'];\n",
            'backend/.env.example' => "APP_KEY=\nDB_PASSWORD=\n",
            'backend/.env.staging' => "APP_ENV=staging\nDB_PASSWORD=".self::SECRET."\n",
            'backend/.gitignore' => "/vendor\n.env\n",
            'backend/tests/ExampleTest.php' => "<?php\n",
            'backend/phpunit.xml' => "<phpunit/>\n",
            'frontend/package.json' => "{\"name\": \"fixture\", \"scripts\": {\"build\": \"vite build\"}}\n",
            'frontend/package-lock.json' => "{\"lockfileVersion\": 3}\n",
            'frontend/.env.production' => 'VITE_API_URL='.self::API_URL."\n",
            'frontend/dist/index.html' => '<!doctype html><script src="/assets/stale-old.js"></script>',
            'frontend/dist/assets/stale-old.js' => 'const api="http://localhost:8000/api/v1";',
            'frontend/src/main.ts' => "console.log('v1');\n",
        ];
        foreach ($files as $path => $contents) {
            File::ensureDirectoryExists(dirname($this->repo.'/'.$path));
            File::put($this->repo.'/'.$path, $contents);
        }
        foreach (['storage/app/public', 'storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs'] as $writable) {
            File::ensureDirectoryExists($this->repo.'/backend/'.$writable);
            File::put($this->repo.'/backend/'.$writable.'/.gitignore', "*\n!.gitignore\n");
        }
        File::ensureDirectoryExists($this->repo.'/frontend/public');
        File::copy(base_path('public/.htaccess'), $this->repo.'/backend/public/.htaccess');
        File::copy(base_path('../frontend/public/.htaccess'), $this->repo.'/frontend/public/.htaccess');
        File::ensureDirectoryExists($this->repo.'/scripts/release');
        File::copy(base_path('../scripts/release/build-release.sh'), $this->repo.'/scripts/release/build-release.sh');
        File::copy(base_path('../scripts/release/validate-release.sh'), $this->repo.'/scripts/release/validate-release.sh');

        $git = 'git -c user.name=p30 -c user.email=p30@example.test -c core.autocrlf=false';
        $result = $this->runBash(['-c', "{$git} init -q && {$git} add -A && {$git} commit -q -m fixture && git rev-parse HEAD"], [], $this->repo);
        $this->assertSame(0, $result['exit'], $result['output']);
        $this->commit = trim(last(explode("\n", trim($result['output']))));
    }

    private function writeFakeTools(): void
    {
        $bin = $this->workDir.'/fakebin';
        File::makeDirectory($bin);
        $tools = [
            'php' => <<<'SH'
                #!/usr/bin/env bash
                echo "php $*" >> "$FAKE_LOG"
                if [[ "$1" == "-r" ]]; then
                  if [[ "$2" == *version_compare* ]]; then [[ -n "${FAKE_PHP_OLD:-}" ]] && exit 1; exit 0; fi
                  echo "${FAKE_PHP_VERSION:-8.2.20}"; exit 0
                fi
                if [[ "$1" == "artisan" && "$2" == "package:discover" ]]; then
                  [[ " ${FAKE_FAIL:-} " == *" discover "* ]] && { echo "discover failed"; exit 1; }
                  mkdir -p bootstrap/cache
                  echo "<?php return [];" > bootstrap/cache/packages.php
                  echo "<?php return [];" > bootstrap/cache/services.php
                fi
                exit 0
                SH,
            'composer' => <<<'SH'
                #!/usr/bin/env bash
                echo "composer $*" >> "$FAKE_LOG"
                case "$1" in
                  validate) [[ -n "${FAKE_LOCK_STALE:-}" ]] && echo "The lock file is not up to date with the latest changes in composer.json."; exit 0 ;;
                  install)
                    [[ " ${FAKE_FAIL:-} " == *" install "* ]] && { echo "Your lock file does not contain a compatible set of packages."; exit 2; }
                    mkdir -p vendor/composer vendor/laravel/framework
                    echo "<?php" > vendor/autoload.php
                    echo '{"packages": [], "dev": false}' > vendor/composer/installed.json
                    echo "framework" > vendor/laravel/framework/README.md
                    [[ -n "${FAKE_DEV_LEAK:-}" ]] && { mkdir -p vendor/phpunit/phpunit; echo x > vendor/phpunit/phpunit/README.md; }
                    exit 0 ;;
                  check-platform-reqs) [[ " ${FAKE_FAIL:-} " == *" platform "* ]] && { echo "ext-gd * missing"; exit 2; }; exit 0 ;;
                  --version) echo "Composer version 2.fake"; exit 0 ;;
                esac
                exit 0
                SH,
            'npm' => <<<'SH'
                #!/usr/bin/env bash
                echo "npm $*" >> "$FAKE_LOG"
                case "$1" in
                  ci) [[ " ${FAKE_FAIL:-} " == *" ci "* ]] && { echo "npm ERR! lockfile mismatch"; exit 1; }; mkdir -p node_modules; exit 0 ;;
                  run)
                    [[ " ${FAKE_FAIL:-} " == *" build "* ]] && { echo "vue-tsc: error TS2304"; exit 2; }
                    out="$5"
                    url="$(grep -E '^VITE_API_URL=' .env.production | cut -d= -f2-)"
                    mkdir -p "$out/assets"
                    printf '<!doctype html><script type="module" src="/assets/app-%s.js"></script>' "$(sha256sum src/main.ts | cut -c1-8)" > "$out/index.html"
                    printf 'const api="%s";%s' "$url" "$(cat src/main.ts)" > "$out/assets/app-$(sha256sum src/main.ts | cut -c1-8).js"
                    cp public/.htaccess "$out/.htaccess"
                    [[ -n "${FAKE_SOURCEMAP:-}" ]] && echo '{}' > "$out/assets/app.js.map"
                    exit 0 ;;
                  --version) echo "10.fake"; exit 0 ;;
                esac
                exit 0
                SH,
        ];
        foreach ($tools as $name => $script) {
            File::put($bin.'/'.$name, str_replace("\r", '', $script)."\n");
            chmod($bin.'/'.$name, 0755);
        }
        File::put($this->workDir.'/fake.log', '');
    }

    /** @return array{exit: int, output: string, calls: list<string>} */
    private function build(array $args, array $env = []): array
    {
        File::put($this->workDir.'/fake.log', '');
        $bin = $this->workDir.'/fakebin';
        $result = $this->runBash(array_merge([$this->repo.'/scripts/release/build-release.sh'], $args), array_merge([
            'PHP_BIN' => $bin.'/php',
            'COMPOSER_BIN' => $bin.'/composer',
            'NPM_BIN' => $bin.'/npm',
            'FAKE_LOG' => $this->workDir.'/fake.log',
            'EXPECTED_API_URL' => self::API_URL,
        ], $env), $this->repo);

        $this->assertStringNotContainsString(self::SECRET, $result['output'], 'secret values must never be printed');
        $result['calls'] = array_values(array_filter(explode("\n", str_replace("\r", '', File::get($this->workDir.'/fake.log')))));

        return $result;
    }

    private function out(string $name = 'rc'): string
    {
        return $this->workDir.'/out/'.$name;
    }

    private function assertNothingProduced(string $out): void
    {
        $this->assertDirectoryDoesNotExist($out);
        $this->assertFileDoesNotExist($out.'.tar.gz');
        $this->assertFileDoesNotExist($out.'.tar.gz.sha256');
        $this->assertSame([], glob(dirname($out).'/.stage-*') ?: [], 'staging directories must be removed');
    }

    /** @return list<string> */
    private function artifactFiles(string $out): array
    {
        return array_map(fn ($f) => str_replace('\\', '/', $f->getRelativePathname()), File::allFiles($out, true));
    }

    public function test_release_mode_builds_a_clean_validated_artifact_from_the_committed_revision(): void
    {
        $out = $this->out();
        $result = $this->build(['--ref', 'HEAD', '--out', $out]);

        $this->assertSame(0, $result['exit'], $result['output']);
        $files = $this->artifactFiles($out);
        $this->assertContains('msa-backend/artisan', $files);
        $this->assertContains('msa-backend/vendor/autoload.php', $files);
        $this->assertContains('msa-backend/bootstrap/cache/packages.php', $files);
        $this->assertContains('public_html/.htaccess', $files);
        $this->assertNotContains('msa-backend/.env.staging', $files);
        $this->assertNotContains('msa-backend/phpunit.xml', $files);
        $this->assertEmpty(array_filter($files, fn ($f) => str_starts_with($f, 'msa-backend/tests/')));
        $this->assertEmpty(array_filter($files, fn ($f) => str_contains($f, 'stale-old')), 'the checked-in dist must never be shipped');
        $this->assertContains('msa-backend/.env.example', $files);

        foreach ($files as $file) {
            $this->assertStringNotContainsString(self::SECRET, File::get($out.'/'.$file), $file);
        }
        $this->assertStringContainsString('Stripping environment files from the backend: .env.staging', $result['output']);
        $this->assertStringContainsString('Release artifact: VALID', $result['output']);

        $manifest = File::get($out.'/RELEASE_MANIFEST.txt');
        $this->assertStringContainsString("source_commit: {$this->commit}", $manifest);
        $this->assertStringContainsString('releasable: yes', $manifest);
        $this->assertStringContainsString('stripped_env_files: .env.staging', $manifest);

        $this->assertFileExists($out.'.tar.gz');
        $this->assertSame(hash_file('sha256', $out.'.tar.gz'), strtok(File::get($out.'.tar.gz.sha256'), ' '));
        $this->assertStringContainsString('SHA-256: '.hash_file('sha256', $out.'.tar.gz'), $result['output']);

        $installs = array_values(array_filter($result['calls'], fn ($c) => str_starts_with($c, 'composer install')));
        $this->assertCount(1, $installs);
        $this->assertStringContainsString('--no-dev', $installs[0]);
        $this->assertContains('npm ci --no-audit --no-fund', $result['calls']);

        $revalidate = $this->runBash([self::releaseScript('validate-release.sh'), $out], ['EXPECTED_API_URL' => self::API_URL]);
        $this->assertSame(0, $revalidate['exit'], $revalidate['output']);
    }

    public function test_release_mode_ignores_uncommitted_changes(): void
    {
        File::put($this->repo.'/frontend/src/main.ts', "console.log('uncommitted');\n");

        $result = $this->build(['--ref', $this->commit, '--out', $this->out()]);

        $this->assertSame(0, $result['exit'], $result['output']);
        $bundle = collect($this->artifactFiles($this->out()))->first(fn ($f) => str_ends_with($f, '.js'));
        $this->assertStringContainsString("console.log('v1')", File::get($this->out().'/'.$bundle));
    }

    public function test_worktree_mode_is_marked_not_releasable_and_includes_local_changes(): void
    {
        File::put($this->repo.'/frontend/src/main.ts', "console.log('uncommitted');\n");

        $result = $this->build(['--worktree', '--out', $this->out()]);

        $this->assertSame(0, $result['exit'], $result['output']);
        $manifest = File::get($this->out().'/RELEASE_MANIFEST.txt');
        $this->assertStringContainsString('releasable: NO', $manifest);
        $this->assertStringContainsString('working_tree_dirty: yes', $manifest);
        $this->assertStringContainsString('Releasable: NO', $result['output']);
        $this->assertNotContains('msa-backend/.env.staging', $this->artifactFiles($this->out()));
        $bundle = collect($this->artifactFiles($this->out()))->first(fn ($f) => str_ends_with($f, '.js'));
        $this->assertStringContainsString('uncommitted', File::get($this->out().'/'.$bundle));
    }

    /** @return array<string, array{array<string, string>, int, string}> */
    public static function failures(): array
    {
        return [
            'php too old' => [['FAKE_PHP_OLD' => '1', 'FAKE_PHP_VERSION' => '8.1.29'], 31, 'older than'],
            'stale composer.lock' => [['FAKE_LOCK_STALE' => '1'], 34, 'composer.lock does not match'],
            'composer install fails' => [['FAKE_FAIL' => 'install'], 33, 'composer install --no-dev failed'],
            'platform requirements missing' => [['FAKE_FAIL' => 'platform'], 33, 'platform requirements'],
            'package discovery fails' => [['FAKE_FAIL' => 'discover'], 33, 'package:discover failed'],
            'npm ci fails' => [['FAKE_FAIL' => 'ci'], 35, 'npm ci failed'],
            'frontend build fails' => [['FAKE_FAIL' => 'build'], 35, 'Frontend build failed'],
            'dev dependency leaks into vendor' => [['FAKE_DEV_LEAK' => '1'], 36, 'failed validation'],
            'build emits source maps' => [['FAKE_SOURCEMAP' => '1'], 36, 'failed validation'],
            'build points at another API' => [['EXPECTED_API_URL' => 'https://api.example.test/api/v1'], 36, 'failed validation'],
        ];
    }

    #[DataProvider('failures')]
    public function test_every_failed_step_stops_the_build_and_leaves_no_artifact(array $env, int $expectedExit, string $mention): void
    {
        $result = $this->build(['--ref', 'HEAD', '--out', $this->out()], $env);

        $this->assertSame($expectedExit, $result['exit'], $result['output']);
        $this->assertStringContainsString($mention, $result['output']);
        $this->assertStringContainsString('No artifact was produced', $result['output']);
        $this->assertNothingProduced($this->out());
    }

    public function test_unknown_revision_is_refused(): void
    {
        $result = $this->build(['--ref', 'no-such-tag', '--out', $this->out()]);

        $this->assertSame(32, $result['exit'], $result['output']);
        $this->assertNothingProduced($this->out());
    }

    public function test_missing_tool_is_refused(): void
    {
        $result = $this->build(['--ref', 'HEAD', '--out', $this->out()], ['COMPOSER_BIN' => $this->workDir.'/fakebin/missing-composer']);

        $this->assertSame(30, $result['exit'], $result['output']);
        $this->assertNothingProduced($this->out());
    }

    public function test_an_existing_artifact_is_never_overwritten(): void
    {
        File::ensureDirectoryExists($this->out());
        File::put($this->out().'/keep.txt', 'previous build');

        $result = $this->build(['--ref', 'HEAD', '--out', $this->out()]);

        $this->assertSame(2, $result['exit'], $result['output']);
        $this->assertSame('previous build', File::get($this->out().'/keep.txt'));
    }

    public function test_usage_errors_exit_2(): void
    {
        $this->assertSame(2, $this->build([])['exit']);
        $this->assertSame(2, $this->build(['--ref', 'HEAD'])['exit']);
        $this->assertSame(2, $this->build(['--bogus'])['exit']);
        $this->assertSame(2, $this->build(['--worktree', '--out'])['exit'], 'a trailing option without a value must not loop');
        $this->assertSame(2, $this->build(['--out', $this->out(), '--ref'])['exit']);
    }
}
