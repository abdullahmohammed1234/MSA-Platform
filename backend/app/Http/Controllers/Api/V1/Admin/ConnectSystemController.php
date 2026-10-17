<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Ems\Models\Event as EmsEvent;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CMS\Announcement;
use App\Models\Conversation;
use App\Models\DeviceSubscription;
use App\Models\DirectMessage;
use App\Models\SupportInquiry;
use App\Services\Security\AuditLogger;
use App\Services\Systems\SystemsControlPlaneService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MSA Connect Management System Controller (Phase 14).
 *
 * Dedicated administrative interface controller for configuring and managing
 * the member-facing MSA Connect application.
 *
 * Architectural boundaries:
 * - Does not own EMS event records (reads EMS as authoritative source of truth).
 * - Does not own CMS communications/bulletins (reads CMS as authoritative source of truth).
 * - Cohorts and mentorship pairing are explicitly out of scope.
 */
class ConnectSystemController extends Controller
{
    public function __construct(
        private SystemsControlPlaneService $systems
    ) {}

    /**
     * GET /api/v1/admin/systems/connect
     * System metadata, ownership contract, and status.
     */
    public function index(Request $request): JsonResponse
    {
        if (! $this->canView($request)) {
            return response()->json(['message' => 'Unauthorized. Required permission: connect.view or system.view.'], 403);
        }

        $app = $this->systems->application('connect');

        return response()->json([
            'success' => true,
            'system' => [
                'name' => 'MSA Connect Management',
                'slug' => 'connect',
                'version' => $app['version'] ?? config('systems.applications.connect.version', '1.0.0'),
                'status' => $app['status'] ?? SystemsControlPlaneService::STATUS_UNKNOWN,
                'health_status' => $app['health_status'] ?? SystemsControlPlaneService::STATUS_UNKNOWN,
                'status_reason' => $app['status_reason'] ?? null,
                'frontend_url' => $app['launch_url'] ?? rtrim((string) config('app.frontend_url', ''), '/').'/connect/admin',
                'admin_path' => '/admin/systems/connect',
                'owns' => config('systems.applications.connect.owns', [
                    'connect_configuration',
                    'connect_integrations',
                    'device_subscriptions',
                ]),
                'does_not_own' => config('systems.applications.connect.does_not_own', [
                    'ems_events',
                    'cms_announcements',
                    'users_identity',
                ]),
                'updated_at' => Carbon::now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * GET /api/v1/admin/systems/connect/health
     * Live operational health diagnostics for MSA Connect.
     */
    public function health(Request $request): JsonResponse
    {
        if (! $this->canView($request)) {
            return response()->json(['message' => 'Unauthorized. Required permission: connect.view or system.view.'], 403);
        }

        // 1. Database Connection & Latency Probe
        $dbStatus = 'operational';
        $dbLatencyMs = 0.0;
        try {
            $start = microtime(true);
            DB::connection()->getPdo();
            DB::select('select 1 as ok');
            $dbLatencyMs = round((microtime(true) - $start) * 1000, 2);
        } catch (\Throwable $e) {
            $dbStatus = 'unavailable';
        }

        // 2. Web Push Service Diagnostics (VAPID presence without secret leaks)
        $vapidConfigured = false;
        $pushStatus = 'operational';
        try {
            $subject = config('webpush.vapid.subject');
            $pubKey = config('webpush.vapid.public_key');
            $privKey = config('webpush.vapid.private_key');
            $vapidConfigured = ! empty($subject) && ! empty($pubKey) && ! empty($privKey);
            if (! $vapidConfigured) {
                $pushStatus = 'degraded';
            }
        } catch (\Throwable $e) {
            $pushStatus = 'unavailable';
        }

        // 3. Cache Diagnostics
        $cacheStatus = 'operational';
        try {
            $testKey = 'connect_probe_'.time();
            Cache::put($testKey, 'active', 5);
            $val = Cache::get($testKey);
            Cache::forget($testKey);
            if ($val !== 'active') {
                $cacheStatus = 'degraded';
            }
        } catch (\Throwable $e) {
            $cacheStatus = 'unavailable';
        }

        // 4. Device Subscription Store Reachability
        $storeStatus = 'operational';
        $deviceCount = 0;
        try {
            if (Schema::hasTable('device_subscriptions')) {
                $deviceCount = DeviceSubscription::count();
            } else {
                $storeStatus = 'unavailable';
            }
        } catch (\Throwable $e) {
            $storeStatus = 'unavailable';
        }

        // Overall status rollup
        $statuses = [$dbStatus, $pushStatus, $cacheStatus, $storeStatus];
        $overall = 'operational';
        if (in_array('unavailable', $statuses, true)) {
            $overall = 'unavailable';
        } elseif (in_array('degraded', $statuses, true)) {
            $overall = 'degraded';
        }

        return response()->json([
            'success' => true,
            'health' => [
                'status' => $overall,
                'database' => [
                    'status' => $dbStatus,
                    'latency_ms' => $dbLatencyMs,
                    'driver' => config('database.default'),
                ],
                'web_push' => [
                    'status' => $pushStatus,
                    'vapid_configured' => $vapidConfigured,
                    'delivery_mode' => 'synchronous',
                    'timeout_seconds' => config('webpush.timeout', 5.0),
                ],
                'cache' => [
                    'status' => $cacheStatus,
                    'driver' => config('cache.default'),
                ],
                'devices_store' => [
                    'status' => $storeStatus,
                    'total_registered_devices' => $deviceCount,
                ],
                'checked_at' => Carbon::now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * GET /api/v1/admin/systems/connect/metrics
     * Grounded Connect metrics without fabricated mock numbers.
     */
    public function metrics(Request $request): JsonResponse
    {
        if (! $this->canView($request)) {
            return response()->json(['message' => 'Unauthorized. Required permission: connect.view or system.view.'], 403);
        }

        $registeredDevices = 0;
        $activeDevices = 0;
        $uniqueDeviceUsers = 0;
        if (Schema::hasTable('device_subscriptions')) {
            $registeredDevices = DeviceSubscription::count();
            $activeDevices = DeviceSubscription::where('is_active', true)->count();
            $uniqueDeviceUsers = DeviceSubscription::where('is_active', true)->distinct('user_id')->count('user_id');
        }

        $totalConversations = Schema::hasTable('conversations') ? Conversation::count() : 0;
        $totalMessages = Schema::hasTable('direct_messages') ? DirectMessage::count() : 0;

        $supportInquiries = 0;
        $openInquiries = 0;
        $resolvedInquiries = 0;
        if (Schema::hasTable('support_inquiries')) {
            $supportInquiries = SupportInquiry::count();
            $openInquiries = SupportInquiry::where('status', 'open')->count();
            $resolvedInquiries = SupportInquiry::whereIn('status', ['resolved', 'closed'])->count();
        }

        return response()->json([
            'success' => true,
            'metrics' => [
                'registered_devices' => $registeredDevices,
                'active_devices' => $activeDevices,
                'unique_device_users' => $uniqueDeviceUsers,
                'total_conversations' => $totalConversations,
                'total_messages' => $totalMessages,
                'support_inquiries' => $supportInquiries,
                'open_inquiries' => $openInquiries,
                'resolved_inquiries' => $resolvedInquiries,
                'updated_at' => Carbon::now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * GET /api/v1/admin/systems/connect/config
     * Inspect Connect administrative configuration.
     */
    public function getConfig(Request $request): JsonResponse
    {
        if (! $this->canView($request)) {
            return response()->json(['message' => 'Unauthorized. Required permission: connect.view or system.view.'], 403);
        }

        return response()->json([
            'success' => true,
            'config' => $this->loadConfig(),
        ]);
    }

    /**
     * PUT /api/v1/admin/systems/connect/config
     * Update Connect administrative configuration with validation and audit logging.
     */
    public function updateConfig(Request $request): JsonResponse
    {
        if (! $this->canManage($request)) {
            return response()->json(['message' => 'Unauthorized. Required permission: connect.manage or system.manage.'], 403);
        }

        $validated = $request->validate([
            'connect_resources_enabled' => 'required|boolean',
            'member_directory_enabled' => 'required|boolean',
            'direct_messaging_enabled' => 'required|boolean',
            'events_feed_enabled' => 'required|boolean',
            'announcements_feed_enabled' => 'required|boolean',
            'webpush_enabled' => 'required|boolean',
            'max_devices_per_user' => 'required|integer|min:1|max:50',
            'delivery_timeout_seconds' => 'required|numeric|min:1|max:30',
            'support_inquiries_enabled' => 'required|boolean',
            'maintenance_mode' => 'required|boolean',
            'maintenance_message' => 'nullable|string|max:255',
        ]);

        $this->saveConfig($validated);

        // System Auditability
        AuditLogger::log(
            'connect.config_updated',
            null,
            'MSA Connect platform configuration updated by administrator.',
            $validated,
            $request->user()?->id,
            'connect',
            'info'
        );

        return response()->json([
            'success' => true,
            'message' => 'MSA Connect configuration saved successfully.',
            'config' => $validated,
        ]);
    }

    /**
     * GET /api/v1/admin/systems/connect/events
     * Expose published events feed from EMS (EMS remains authoritative source of truth).
     */
    public function events(Request $request): JsonResponse
    {
        if (! $this->canView($request)) {
            return response()->json(['message' => 'Unauthorized. Required permission: connect.view or system.view.'], 403);
        }

        $config = $this->loadConfig();
        if (! ($config['events_feed_enabled'] ?? true)) {
            return response()->json([
                'success' => true,
                'authoritative_source' => 'EMS (Event Management System)',
                'ownership_notice' => 'EMS is the sole authoritative owner of events, ticketing, registrations, and payment flows. MSA Connect does not duplicate event management.',
                'feed_enabled' => false,
                'events' => [
                    'data' => [],
                    'total' => 0,
                ],
            ]);
        }

        if (! Schema::hasTable('ems_events')) {
            return response()->json([
                'success' => true,
                'data' => [],
                'message' => 'EMS events store is not initialized.',
            ]);
        }

        $query = EmsEvent::query();

        if ($request->filled('search')) {
            $search = (string) $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        $perPage = min(50, max(5, (int) $request->input('per_page', 15)));
        $events = $query->orderBy('start_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'authoritative_source' => 'EMS (Event Management System)',
            'ownership_notice' => 'EMS is the sole authoritative owner of events, ticketing, registrations, and payment flows. MSA Connect does not duplicate event management.',
            'events' => $events,
        ]);
    }

    /**
     * GET /api/v1/admin/systems/connect/announcements
     * Expose communications / bulletin feed from CMS (CMS remains authoritative source of truth).
     */
    public function announcements(Request $request): JsonResponse
    {
        if (! $this->canView($request)) {
            return response()->json(['message' => 'Unauthorized. Required permission: connect.view or system.view.'], 403);
        }

        $config = $this->loadConfig();
        if (! ($config['announcements_feed_enabled'] ?? true)) {
            return response()->json([
                'success' => true,
                'authoritative_source' => 'CMS (Content Management System)',
                'ownership_notice' => 'CMS is the authoritative owner of platform communications, bulletins, and public announcements. MSA Connect does not duplicate content storage.',
                'feed_enabled' => false,
                'announcements' => [
                    'data' => [],
                    'total' => 0,
                ],
            ]);
        }

        if (! Schema::hasTable('announcements')) {
            return response()->json([
                'success' => true,
                'data' => [],
                'message' => 'CMS announcements store is not initialized.',
            ]);
        }

        $query = Announcement::query()->with('author:id,name,email');

        if ($request->filled('search')) {
            $search = (string) $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('summary', 'like', "%{$search}%");
            });
        }

        $perPage = min(50, max(5, (int) $request->input('per_page', 15)));
        $announcements = $query->orderBy('published_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'authoritative_source' => 'CMS (Content Management System)',
            'ownership_notice' => 'CMS is the authoritative owner of platform communications, bulletins, and public announcements. MSA Connect does not duplicate content storage.',
            'announcements' => $announcements,
        ]);
    }

    /**
     * GET /api/v1/admin/systems/connect/audit
     * View audit log history for MSA Connect.
     */
    public function audit(Request $request): JsonResponse
    {
        if (! $this->canView($request)) {
            return response()->json(['message' => 'Unauthorized. Required permission: connect.view or system.view.'], 403);
        }

        if (! Schema::hasTable('audit_logs')) {
            return response()->json([
                'success' => true,
                'data' => [],
            ]);
        }

        $perPage = min(50, max(5, (int) $request->input('per_page', 20)));

        $logs = AuditLog::where(function ($q) {
            $q->where('application', 'connect')
              ->orWhere('action', 'like', 'connect.%');
        })
            ->with('user:id,name,email')
            ->orderBy('id', 'desc')
            ->paginate($perPage);

        // Privacy boundary enforcement: sanitize sensitive secrets/tokens from payloads
        $logs->getCollection()->transform(function ($log) {
            if (is_array($log->payload)) {
                $log->payload = $this->sanitizeAuditPayload($log->payload);
            }
            return $log;
        });

        return response()->json([
            'success' => true,
            'logs' => $logs,
        ]);
    }

    private function sanitizeAuditPayload(array $payload): array
    {
        $sensitiveKeys = ['password', 'secret', 'token', 'private_key', 'auth_token', 'p256dh', 'authorization'];
        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = $this->sanitizeAuditPayload($value);
            } elseif (is_string($key)) {
                $lowerKey = strtolower($key);
                foreach ($sensitiveKeys as $sensitive) {
                    if (str_contains($lowerKey, $sensitive)) {
                        $payload[$key] = '[REDACTED]';
                        break;
                    }
                }
            }
        }
        return $payload;
    }

    private function canView(Request $request): bool
    {
        $user = $request->user();
        if (! $user) {
            return false;
        }

        if ($user->hasRole('super-admin') || $user->hasRole('admin') || $user->hasRole('connect-administrator') || $user->hasRole('connect-staff')) {
            return true;
        }

        return $user->hasPermissionTo('view_systems_health')
            || $user->hasPermissionTo('system.view')
            || $user->hasPermissionTo('connect.view')
            || $user->hasPermissionTo('connect.manage');
    }

    private function canManage(Request $request): bool
    {
        $user = $request->user();
        if (! $user) {
            return false;
        }

        if ($user->hasRole('super-admin') || $user->hasRole('admin') || $user->hasRole('connect-administrator')) {
            return true;
        }

        return $user->hasPermissionTo('system.manage') || $user->hasPermissionTo('connect.manage');
    }

    public static function getPersistedConfig(): array
    {
        $filePath = storage_path('app/connect_config.json');

        $defaults = [
            'connect_resources_enabled' => (bool) config('features.connect_resources_enabled', false),
            'member_directory_enabled' => true,
            'direct_messaging_enabled' => true,
            'events_feed_enabled' => true,
            'announcements_feed_enabled' => true,
            'webpush_enabled' => true,
            'max_devices_per_user' => (int) config('webpush.max_devices_per_user', 10),
            'delivery_timeout_seconds' => (float) config('webpush.timeout', 5.0),
            'support_inquiries_enabled' => true,
            'maintenance_mode' => false,
            'maintenance_message' => null,
        ];

        if (file_exists($filePath)) {
            $custom = json_decode((string) file_get_contents($filePath), true);
            if (is_array($custom)) {
                return array_merge($defaults, $custom);
            }
        }

        return $defaults;
    }

    private function loadConfig(): array
    {
        return self::getPersistedConfig();
    }

    private function saveConfig(array $config): void
    {
        $filePath = storage_path('app/connect_config.json');
        $dir = dirname($filePath);
        if (! file_exists($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($filePath, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        Cache::forget('website_announcements');
    }
}
