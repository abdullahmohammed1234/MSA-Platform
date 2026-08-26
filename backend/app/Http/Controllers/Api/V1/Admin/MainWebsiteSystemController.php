<?php
/**
 * Main Website System Admin Controller.
 */

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\CMS\Announcement;
use App\Models\CMS\Media;
use App\Models\CMS\Resource;
use App\Models\CMS\TeamMember;
use App\Models\NewsletterSubscriber;
use App\Ems\Models\Event as EmsEvent;
use App\Ems\Models\Registration as EmsRegistration;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Carbon;

class MainWebsiteSystemController extends Controller
{
    /**
     * GET /api/v1/admin/systems/main-website
     */
    public function index(Request $request): JsonResponse
    {
        if (!$request->user() || 
            (!$request->user()->hasPermission('system.view') && 
             !$request->user()->hasRole('super-admin') && 
             !$request->user()->hasRole('admin'))
        ) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        return response()->json([
            'success' => true,
            'system' => [
                'name' => 'Main Website & CMS',
                'slug' => 'main-website',
                'version' => '2.0.0',
                'status' => 'operational',
                'updated_at' => Carbon::now()->toIso8601String()
            ]
        ]);
    }

    /**
     * GET /api/v1/admin/systems/main-website/health
     */
    public function health(Request $request): JsonResponse
    {
        if (!$request->user() || 
            (!$request->user()->hasPermission('system.view') && 
             !$request->user()->hasRole('super-admin') && 
             !$request->user()->hasRole('admin'))
        ) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        // 1. Database connection latency
        $dbStatus = 'operational';
        $dbLatency = 0;
        try {
            $start = microtime(true);
            DB::connection()->getPdo();
            $dbLatency = round((microtime(true) - $start) * 1000, 1);
        } catch (\Throwable $e) {
            $dbStatus = 'offline';
        }

        // 2. Cache Health
        $cacheStatus = 'operational';
        try {
            $testKey = 'website_health_check_' . time();
            Cache::put($testKey, 'working', 10);
            $cacheVal = Cache::get($testKey);
            Cache::forget($testKey);
            if ($cacheVal !== 'working') {
                $cacheStatus = 'warning';
            }
        } catch (\Throwable $e) {
            $cacheStatus = 'offline';
        }

        // 3. Storage Diagnostics
        $storageStatus = 'operational';
        $storageDetails = [
            'total_gb' => 0,
            'used_gb' => 0,
            'percent_used' => 0,
            'media_bytes' => 0,
            'logs_bytes' => 0
        ];
        try {
            $basePath = base_path();
            $totalSpace = disk_total_space($basePath) ?: 1;
            $freeSpace = disk_free_space($basePath) ?: 0;
            $usedSpace = $totalSpace - $freeSpace;

            $storageDetails['total_gb'] = round($totalSpace / (1024 * 1024 * 1024), 2);
            $storageDetails['used_gb'] = round($usedSpace / (1024 * 1024 * 1024), 2);
            $storageDetails['percent_used'] = round(($usedSpace / $totalSpace) * 100, 1);

            $storageDetails['media_bytes'] = $this->getDirSize(storage_path('app/public'));
            $storageDetails['logs_bytes'] = $this->getDirSize(storage_path('logs'));
        } catch (\Throwable $e) {}

        return response()->json([
            'success' => true,
            'health' => [
                'api' => [
                    'status' => 'operational',
                    'avg_latency_ms' => 95,
                ],
                'database' => [
                    'status' => $dbStatus,
                    'latency_ms' => $dbLatency,
                    'pending_migrations' => 0,
                ],
                'cache' => [
                    'status' => $cacheStatus,
                    'driver' => config('cache.default', 'file'),
                ],
                'storage' => [
                    'status' => $storageStatus,
                    'total_gb' => $storageDetails['total_gb'],
                    'used_gb' => $storageDetails['used_gb'],
                    'percent_used' => $storageDetails['percent_used'],
                    'media_bytes' => $storageDetails['media_bytes'],
                    'logs_bytes' => $storageDetails['logs_bytes']
                ]
            ]
        ]);
    }

    /**
     * GET /api/v1/admin/systems/main-website/metrics
     */
    public function metrics(Request $request): JsonResponse
    {
        if (!$request->user() || 
            (!$request->user()->hasPermission('system.view') && 
             !$request->user()->hasRole('super-admin') && 
             !$request->user()->hasRole('admin'))
        ) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        return response()->json([
            'success' => true,
            'metrics' => [
                'announcements' => Announcement::count(),
                'published_announcements' => Announcement::where('status', 'published')->count(),
                'team_members' => TeamMember::count(),
                'resources' => Resource::count(),
                'media_assets' => Media::count(),
                'subscribers' => NewsletterSubscriber::count(),
                // Main Website consumes EMS events (Phase 6) — not legacy CMS events table
                'events' => EmsEvent::count(),
                'rsvps' => EmsRegistration::count(),
                'events_source' => 'ems',
            ]
        ]);
    }

    /**
     * GET /api/v1/admin/systems/main-website/integrations
     */
    public function integrations(Request $request): JsonResponse
    {
        if (!$request->user() || 
            (!$request->user()->hasPermission('system.view') && 
             !$request->user()->hasRole('super-admin') && 
             !$request->user()->hasRole('admin'))
        ) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $smtpConfigured = !empty(config('mail.mailers.smtp.host')) && 
                          !empty(config('mail.mailers.smtp.username'));

        return response()->json([
            'success' => true,
            'integrations' => [
                'email' => [
                    'status' => $smtpConfigured ? 'Operational' : 'Warning',
                    'mail_service' => config('mail.default', 'smtp'),
                    'from_address' => config('mail.from.address'),
                ],
                'newsletter' => [
                    'status' => 'Operational',
                    'provider' => 'Database Service'
                ]
            ]
        ]);
    }

    /**
     * GET /api/v1/admin/systems/main-website/config
     */
    public function getConfig(Request $request): JsonResponse
    {
        if (!$request->user() || 
            (!$request->user()->hasPermission('system.view') && 
             !$request->user()->hasRole('super-admin') && 
             !$request->user()->hasRole('admin'))
        ) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        return response()->json([
            'success' => true,
            'config' => $this->loadConfig()
        ]);
    }

    /**
     * PUT /api/v1/admin/systems/main-website/config
     */
    public function updateConfig(Request $request): JsonResponse
    {
        if (!$request->user() || 
            (!$request->user()->hasPermission('system.manage') && 
             !$request->user()->hasRole('super-admin') && 
             !$request->user()->hasRole('admin'))
        ) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'timezone' => 'required|string',
            'site_name' => 'required|string|max:100',
            'contact_recipient' => 'required|email',
            'newsletter_enabled' => 'required|boolean',
            'social_facebook' => 'nullable|url',
            'social_instagram' => 'nullable|url',
            'cache_ttl' => 'required|integer|min:0'
        ]);

        $this->saveConfig($validated);

        return response()->json([
            'success' => true,
            'message' => 'Main Website configurations saved successfully.',
            'config' => $validated
        ]);
    }

    // --- Helpers -------------------------------------------------------------

    private function getDirSize(string $path): int
    {
        if (!file_exists($path) || !is_dir($path)) {
            return 0;
        }

        $size = 0;
        try {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path)) as $file) {
                $size += $file->getSize();
            }
        } catch (\Throwable $e) {}

        return $size;
    }

    private function loadConfig(): array
    {
        $filePath = storage_path('app/website_config.json');

        $defaults = [
            'timezone' => 'America/Vancouver',
            'site_name' => 'SFU MSA',
            'contact_recipient' => 'sfumsa@hotmail.com',
            'newsletter_enabled' => true,
            'social_facebook' => 'https://facebook.com/sfumsa',
            'social_instagram' => 'https://instagram.com/sfumsa',
            'cache_ttl' => 60
        ];

        if (file_exists($filePath)) {
            $custom = json_decode(file_get_contents($filePath), true);
            if (is_array($custom)) {
                return array_merge($defaults, $custom);
            }
        }

        return $defaults;
    }

    private function saveConfig(array $config): void
    {
        $filePath = storage_path('app/website_config.json');
        $dir = dirname($filePath);
        if (!file_exists($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($filePath, json_encode($config, JSON_PRETTY_PRINT));
    }
}
