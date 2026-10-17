<?php

namespace Tests\Feature\Phase30;

use Illuminate\Support\Facades\File;

/**
 * Runs the scripts in /scripts/release under bash in throwaway directories. Nothing here
 * builds a real release, touches a real host or reads a real environment file.
 */
trait RunsReleaseScripts
{
    protected string $workDir;

    protected function makeWorkDir(string $prefix): void
    {
        if (self::bash() === null) {
            $this->markTestSkipped('bash with GNU coreutils not found (set P29_BASH).');
        }

        $this->workDir = str_replace('\\', '/', sys_get_temp_dir()).'/'.$prefix.'-'.bin2hex(random_bytes(6));
        File::makeDirectory($this->workDir, 0755, true);
    }

    protected function removeWorkDir(): void
    {
        if (isset($this->workDir) && is_dir($this->workDir)) {
            File::deleteDirectory($this->workDir);
        }
    }

    protected static function bash(): ?string
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

    protected static function releaseScript(string $name): string
    {
        return str_replace('\\', '/', realpath(base_path('../scripts/release/'.$name)));
    }

    /**
     * @param  list<string>  $args
     * @param  array<string, string>  $env
     * @return array{exit: int, output: string}
     */
    protected function runBash(array $args, array $env = [], ?string $cwd = null): array
    {
        $outFile = $this->workDir.'/.bash-output';
        $process = proc_open(
            array_merge([self::bash()], $args),
            [0 => ['pipe', 'r'], 1 => ['file', $outFile, 'w'], 2 => ['file', $outFile, 'a']],
            $pipes,
            $cwd ?? $this->workDir,
            array_merge(getenv(), $env)
        );
        fclose($pipes[0]);
        $exit = proc_close($process);
        $output = (string) File::get($outFile);
        File::delete($outFile);

        return ['exit' => $exit, 'output' => $output];
    }

    /** Writes a minimal artifact that satisfies validate-release.sh. */
    protected function writeValidArtifact(string $dir, string $apiUrl): void
    {
        $files = [
            'msa-backend/artisan' => "#!/usr/bin/env php\n<?php\n",
            'msa-backend/public/index.php' => "<?php\n",
            'msa-backend/vendor/autoload.php' => "<?php\n",
            'msa-backend/vendor/composer/installed.json' => '{"packages": [], "dev": false, "dev-package-names": []}',
            'msa-backend/vendor/laravel/framework/README.md' => "framework\n",
            'msa-backend/composer.lock' => "{}\n",
            'msa-backend/bootstrap/app.php' => "<?php\n",
            'msa-backend/bootstrap/cache/.gitignore' => "*\n!.gitignore\n",
            'msa-backend/bootstrap/cache/packages.php' => "<?php return [];\n",
            'msa-backend/config/app.php' => "<?php return ['key' => env('APP_KEY')];\n",
            'msa-backend/.env.example' => "APP_KEY=\nDB_PASSWORD=\n",
            'public_html/index.html' => '<!doctype html><html><head><script type="module" crossorigin src="/assets/index-abc123.js"></script>'
                .'<link rel="stylesheet" crossorigin href="/assets/index-abc123.css"></head><body><div id="app"></div></body></html>',
            'public_html/assets/index-abc123.js' => 'const api="'.$apiUrl.'";if(location.hostname==="localhost"){}',
            'public_html/assets/index-abc123.css' => 'body{margin:0}',
            'public_html/robots.txt' => "User-agent: *\n",
        ];
        foreach ($files as $path => $contents) {
            File::ensureDirectoryExists(dirname($dir.'/'.$path));
            File::put($dir.'/'.$path, $contents);
        }
        File::copy(base_path('composer.json'), $dir.'/msa-backend/composer.json');
        File::copy(base_path('public/.htaccess'), $dir.'/msa-backend/public/.htaccess');
        File::copy(base_path('../frontend/public/.htaccess'), $dir.'/public_html/.htaccess');
        foreach (['storage/app/public', 'storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs'] as $writable) {
            File::ensureDirectoryExists($dir.'/msa-backend/'.$writable);
            File::put($dir.'/msa-backend/'.$writable.'/.gitignore', "*\n!.gitignore\n");
        }
    }
}
