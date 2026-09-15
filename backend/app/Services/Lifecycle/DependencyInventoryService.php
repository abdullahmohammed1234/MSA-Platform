<?php

namespace App\Services\Lifecycle;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class DependencyInventoryService
{
    private const CRON_HEARTBEAT_KEY = 'platform.cron.last_heartbeat';

    /**
     * Get complete dependency and service health inventory.
     */
    public function getDependencyHealth(): array
    {
        $checkedAt = now()->toIso8601String();

        $database = $this->evaluateDatabase();
        $cache = $this->evaluateCache();
        $queue = $this->evaluateQueue();
        $scheduler = $this->evaluateScheduler();
        $mail = $this->evaluateMail();
        $storage = $this->evaluateStorage();
        $square = $this->evaluateSquareIntegration();

        $services = [
            'database' => $database,
            'cache' => $cache,
            'queue' => $queue,
            'scheduler' => $scheduler,
            'mail' => $mail,
            'storage' => $storage,
            'square' => $square,
        ];

        // Determine overall dependency status
        $statuses = array_column($services, 'status');
        $overallStatus = match (true) {
            in_array('UNAVAILABLE', $statuses, true) => 'DEGRADED',
            in_array('DEGRADED', $statuses, true) => 'DEGRADED',
            in_array('NOT_VERIFIED', $statuses, true) || in_array('UNKNOWN', $statuses, true) => 'HEALTHY_WITH_UNVERIFIED_SIGNALS',
            default => 'HEALTHY',
        };

        return [
            'overall_status' => $overallStatus,
            'checked_at' => $checkedAt,
            'services' => $services,
        ];
    }

    private function evaluateDatabase(): array
    {
        try {
            DB::connection()->getPdo();
            return [
                'name' => 'MySQL Database Primary',
                'category' => 'database',
                'status' => 'HEALTHY',
                'driver' => config('database.default', 'mysql'),
                'details' => 'Database connection active and responsive.',
                'last_checked_at' => now()->toIso8601String(),
                'limitations' => null,
            ];
        } catch (Throwable $e) {
            return [
                'name' => 'MySQL Database Primary',
                'category' => 'database',
                'status' => 'UNAVAILABLE',
                'driver' => config('database.default', 'mysql'),
                'details' => 'Database connection error: ' . $e->getMessage(),
                'last_checked_at' => now()->toIso8601String(),
                'limitations' => null,
            ];
        }
    }

    private function evaluateCache(): array
    {
        try {
            $key = 'platform.lifecycle.cache_test_' . uniqid();
            Cache::put($key, 'ok', 10);
            $val = Cache::get($key);
            Cache::forget($key);

            if ($val === 'ok') {
                return [
                    'name' => 'Cache System',
                    'category' => 'cache',
                    'status' => 'HEALTHY',
                    'driver' => config('cache.default', 'file'),
                    'details' => 'Cache store write/read verified successfully.',
                    'last_checked_at' => now()->toIso8601String(),
                    'limitations' => null,
                ];
            }

            return [
                'name' => 'Cache System',
                'category' => 'cache',
                'status' => 'DEGRADED',
                'driver' => config('cache.default', 'file'),
                'details' => 'Cache store read back incorrect value.',
                'last_checked_at' => now()->toIso8601String(),
                'limitations' => null,
            ];
        } catch (Throwable $e) {
            return [
                'name' => 'Cache System',
                'category' => 'cache',
                'status' => 'UNAVAILABLE',
                'driver' => config('cache.default', 'file'),
                'details' => 'Cache access error: ' . $e->getMessage(),
                'last_checked_at' => now()->toIso8601String(),
                'limitations' => null,
            ];
        }
    }

    private function evaluateQueue(): array
    {
        try {
            $driver = config('queue.default', 'sync');
            $failedJobsCount = 0;
            if (Schema::hasTable('failed_jobs')) {
                $failedJobsCount = DB::table('failed_jobs')->count();
            }

            $status = 'HEALTHY';
            if ($failedJobsCount > 0) {
                $status = 'DEGRADED';
            }

            return [
                'name' => 'Queue System',
                'category' => 'queue',
                'status' => $status,
                'driver' => $driver,
                'details' => "Queue driver: {$driver}. Failed jobs: {$failedJobsCount}.",
                'failed_jobs' => $failedJobsCount,
                'last_checked_at' => now()->toIso8601String(),
                'limitations' => 'Background worker execution mode relies on cPanel queue workers or scheduled runner.',
            ];
        } catch (Throwable $e) {
            return [
                'name' => 'Queue System',
                'category' => 'queue',
                'status' => 'UNAVAILABLE',
                'driver' => config('queue.default', 'sync'),
                'details' => 'Queue evaluation error: ' . $e->getMessage(),
                'failed_jobs' => 0,
                'last_checked_at' => now()->toIso8601String(),
                'limitations' => null,
            ];
        }
    }

    private function evaluateScheduler(): array
    {
        $lastHeartbeat = Cache::get(self::CRON_HEARTBEAT_KEY);
        if ($lastHeartbeat) {
            $minutesAgo = Carbon::parse($lastHeartbeat)->diffInMinutes(now());
            if ($minutesAgo <= 15) {
                return [
                    'name' => 'Laravel Scheduler',
                    'category' => 'scheduler',
                    'status' => 'HEALTHY',
                    'driver' => 'cPanel Cron / Artisan Schedule',
                    'details' => "Scheduler heartbeat verified {$minutesAgo} minute(s) ago.",
                    'last_heartbeat' => $lastHeartbeat,
                    'last_checked_at' => now()->toIso8601String(),
                    'limitations' => null,
                ];
            }
        }

        return [
            'name' => 'Laravel Scheduler',
            'category' => 'scheduler',
            'status' => 'NOT_VERIFIED',
            'driver' => 'cPanel Cron / Artisan Schedule',
            'details' => 'No active scheduler heartbeat recorded in the last 15 minutes.',
            'last_heartbeat' => $lastHeartbeat,
            'last_checked_at' => now()->toIso8601String(),
            'limitations' => 'Shared-hosting cPanel cron execution cannot be directly polled without runtime heartbeat.',
        ];
    }

    private function evaluateMail(): array
    {
        $driver = config('mail.default', 'smtp');
        $host = config('mail.mailers.smtp.host');

        if (empty($host) && $driver === 'smtp') {
            return [
                'name' => 'Mail Delivery System',
                'category' => 'mail',
                'status' => 'NOT_CONFIGURED',
                'driver' => $driver,
                'details' => 'SMTP mail host is not configured.',
                'last_checked_at' => now()->toIso8601String(),
                'limitations' => null,
            ];
        }

        return [
            'name' => 'Mail Delivery System',
            'category' => 'mail',
            'status' => 'NOT_VERIFIED',
            'driver' => $driver,
            'details' => "Mail driver configured as {$driver}. Automatic test emails disabled for production safety.",
            'last_checked_at' => now()->toIso8601String(),
            'limitations' => 'Provider liveliness requires explicit out-of-band email delivery verification.',
        ];
    }

    private function evaluateStorage(): array
    {
        try {
            $storagePath = storage_path();
            $isWritable = is_writable($storagePath);

            if ($isWritable) {
                return [
                    'name' => 'Filesystem Storage',
                    'category' => 'storage',
                    'status' => 'HEALTHY',
                    'driver' => config('filesystems.default', 'local'),
                    'details' => 'Storage directory is writeable and accessible.',
                    'last_checked_at' => now()->toIso8601String(),
                    'limitations' => null,
                ];
            }

            return [
                'name' => 'Filesystem Storage',
                'category' => 'storage',
                'status' => 'UNAVAILABLE',
                'driver' => config('filesystems.default', 'local'),
                'details' => 'Storage directory is NOT writeable.',
                'last_checked_at' => now()->toIso8601String(),
                'limitations' => null,
            ];
        } catch (Throwable $e) {
            return [
                'name' => 'Filesystem Storage',
                'category' => 'storage',
                'status' => 'UNAVAILABLE',
                'driver' => config('filesystems.default', 'local'),
                'details' => 'Storage evaluation error: ' . $e->getMessage(),
                'last_checked_at' => now()->toIso8601String(),
                'limitations' => null,
            ];
        }
    }

    private function evaluateSquareIntegration(): array
    {
        $appId = config('services.square.application_id') ?? env('SQUARE_APPLICATION_ID');
        if (!empty($appId)) {
            return [
                'name' => 'Square Payment Integration',
                'category' => 'payment',
                'status' => 'HEALTHY',
                'driver' => 'Square API',
                'details' => 'Square API Application ID configured.',
                'last_checked_at' => now()->toIso8601String(),
                'limitations' => 'API connection validated via operational transactions.',
            ];
        }

        return [
            'name' => 'Square Payment Integration',
            'category' => 'payment',
            'status' => 'NOT_CONFIGURED',
            'driver' => 'Square API',
            'details' => 'Square API credentials not configured in environment.',
            'last_checked_at' => now()->toIso8601String(),
            'limitations' => null,
        ];
    }
}
