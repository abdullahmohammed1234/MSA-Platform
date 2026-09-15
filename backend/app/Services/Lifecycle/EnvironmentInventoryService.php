<?php

namespace App\Services\Lifecycle;

use Illuminate\Support\Facades\DB;
use Throwable;

class EnvironmentInventoryService
{
    private const SECRET_PATTERNS = [
        'password', 'secret', 'key', 'token', 'apiKey', 'api_key', 'private', 'credential',
        'auth', 'bearer', 'pass', 'signature', 'access_key', 'encrypt'
    ];

    /**
     * Get safe environment inventory payload.
     */
    public function getEnvironmentInventory(): array
    {
        $dbVersion = $this->detectDatabaseVersion();

        return [
            'environment' => config('app.env', 'production'),
            'debug_mode' => (bool) config('app.debug', false),
            'laravel_version' => app()->version(),
            'php_version' => PHP_VERSION,
            'database_driver' => config('database.default', 'mysql'),
            'database_version' => $dbVersion,
            'cache_driver' => config('cache.default', 'file'),
            'queue_driver' => config('queue.default', 'sync'),
            'filesystem_driver' => config('filesystems.default', 'local'),
            'mail_transport' => config('mail.default', 'smtp'),
            'timezone' => config('app.timezone', 'UTC'),
            'locale' => config('app.locale', 'en'),
            'enabled_capabilities' => [
                'ems' => true,
                'donations' => true,
                'store' => true,
                'mlibms' => true,
                'communications' => true,
                'volunteering' => true,
                'operations' => true,
                'governance' => true,
                'lifecycle' => true,
            ],
            'configured_services' => [
                'database' => 'CONFIGURED',
                'cache' => 'CONFIGURED',
                'queue' => config('queue.default') !== 'sync' ? 'CONFIGURED' : 'SYNC_DEFAULT',
                'mail' => !empty(config('mail.mailers.smtp.host')) ? 'CONFIGURED' : 'NOT_CONFIGURED',
                'square' => !empty(config('services.square.application_id')) ? 'CONFIGURED' : 'NOT_CONFIGURED',
            ],
        ];
    }

    /**
     * Get application release and version metadata.
     */
    public function getReleaseMetadata(): array
    {
        $activeRelease = null;
        try {
            if (class_exists(\App\Models\PlatformRelease::class)) {
                $activeRelease = \App\Models\PlatformRelease::where('is_active', true)->first();
            }
        } catch (\Throwable) {
            // Ignore database errors
        }

        $appVersion = $activeRelease ? $activeRelease->application_version : '1.30.0';
        $frontendBuild = $activeRelease ? $activeRelease->frontend_build : '2026.09.13-phase30';
        $releaseId = $activeRelease ? $activeRelease->release_identifier : env('APP_RELEASE_ID', config('app.version', 'v1.30.0-prod'));
        $deployedAt = $activeRelease && $activeRelease->released_at ? $activeRelease->released_at->toIso8601String() : env('APP_DEPLOYED_AT');

        if (!$deployedAt && file_exists(base_path('composer.json'))) {
            $mtime = @filemtime(base_path('composer.json'));
            if ($mtime) {
                $deployedAt = date('c', $mtime);
            }
        }

        return [
            'application_version' => $appVersion,
            'api_version' => 'v1',
            'frontend_build' => $frontendBuild,
            'release_identifier' => $releaseId,
            'deployment_detected_at' => $deployedAt ?? 'UNKNOWN',
            'deployment_detection_method' => $activeRelease ? 'database_release_registry' : ($deployedAt ? 'composer_manifest_mtime' : 'UNKNOWN'),
            'hosting_environment' => 'cPanel / Shared Hosting (PHP-FPM + MySQL)',
        ];
    }

    /**
     * Safely redact configuration values.
     */
    public function sanitizeConfig(array $config): array
    {
        $sanitized = [];
        foreach ($config as $key => $value) {
            if (is_array($value)) {
                $sanitized[$key] = $this->sanitizeConfig($value);
                continue;
            }

            if ($this->isSecretKey((string) $key)) {
                $sanitized[$key] = empty($value) ? 'NOT_CONFIGURED' : '[REDACTED]';
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    private function isSecretKey(string $key): bool
    {
        $lowerKey = strtolower($key);
        foreach (self::SECRET_PATTERNS as $pattern) {
            if (str_contains($lowerKey, $pattern)) {
                return true;
            }
        }
        return false;
    }

    private function detectDatabaseVersion(): string
    {
        try {
            $pdo = DB::connection()->getPdo();
            return $pdo->getAttribute(\PDO::ATTR_SERVER_VERSION) ?? 'UNKNOWN';
        } catch (Throwable) {
            return 'UNKNOWN';
        }
    }
}
