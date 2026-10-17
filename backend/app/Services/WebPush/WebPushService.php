<?php

namespace App\Services\WebPush;

use App\Models\DeviceSubscription;
use App\Models\User;
use App\Services\WebPush\PushEndpointValidator;
use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class WebPushService
{
    /**
     * In-memory fake storage for testing.
     *
     * @var array|null
     */
    protected static ?array $fakeDispatches = null;

    /**
     * In-memory idempotency fallback cache for current execution.
     * Prevents unintended duplicate deliveries for identical notification events.
     *
     * @var array<string, bool>
     */
    protected array $dispatchedInRequest = [];

    /**
     * Enable faking for automated tests.
     */
    public static function fake(): void
    {
        self::$fakeDispatches = [];
    }

    /**
     * Reset or disable faking.
     */
    public static function stopFaking(): void
    {
        self::$fakeDispatches = null;
    }

    /**
     * Check if the service is currently faking delivery.
     */
    public static function isFaking(): bool
    {
        return self::$fakeDispatches !== null;
    }

    /**
     * Get recorded fake dispatches.
     */
    public static function getFakeDispatches(): array
    {
        return self::$fakeDispatches ?? [];
    }

    /**
     * Clear recorded fake dispatches without disabling fake mode.
     */
    public static function resetFakeDispatches(): void
    {
        if (self::$fakeDispatches !== null) {
            self::$fakeDispatches = [];
        }
    }

    /**
     * Clear the per-request idempotency cache (useful in tests and between requests).
     */
    public function clearIdempotencyCache(): void
    {
        if (app()->bound('request')) {
            app('request')->attributes->remove('__webpush_dispatched');
        }
        $this->dispatchedInRequest = [];
    }

    /**
     * Check if the notification has already been dispatched in the current request lifecycle.
     */
    protected function isDispatchedInCurrentRequest(string $key): bool
    {
        if (app()->bound('request')) {
            $request = app('request');
            $dispatched = $request->attributes->get('__webpush_dispatched', []);
            return isset($dispatched[$key]);
        }

        return isset($this->dispatchedInRequest[$key]);
    }

    /**
     * Record notification dispatch for the current request lifecycle.
     */
    protected function recordDispatchInCurrentRequest(string $key): void
    {
        if (app()->bound('request')) {
            $request = app('request');
            $dispatched = $request->attributes->get('__webpush_dispatched', []);
            $dispatched[$key] = true;
            $request->attributes->set('__webpush_dispatched', $dispatched);
            return;
        }

        $this->dispatchedInRequest[$key] = true;
    }

    /**
     * Public entrypoint: Attempt synchronous Web Push delivery with transaction-boundary guarantee.
     *
     * If an outer database transaction is active, execution is deferred until post-commit.
     * If the transaction rolls back, this callback is never executed.
     *
     * @param mixed $notifiable
     * @param Notification $notification
     * @return void
     */
    public function deliver($notifiable, Notification $notification): void
    {
        if (!$notifiable instanceof User) {
            return;
        }

        // Defer until transaction commit if currently inside a database transaction
        if (DB::transactionLevel() > 0) {
            DB::afterCommit(function () use ($notifiable, $notification) {
                $this->executeDelivery($notifiable, $notification);
            });
        } else {
            $this->executeDelivery($notifiable, $notification);
        }
    }

    /**
     * Execute the synchronous delivery to the user's active device subscriptions.
     *
     * @param User $user
     * @param Notification $notification
     * @return void
     */
    public function executeDelivery(User $user, Notification $notification): void
    {
        try {
            // 0. Check platform-wide master enablement from Connect configuration
            if (! $this->isWebPushMasterEnabled()) {
                return;
            }

            // 1. Check user preferences with documented fallback defaults
            $preferences = $user->notificationPreferences;
            $pushEnabled = $preferences?->push_enabled ?? true;

            if (!$pushEnabled) {
                return;
            }

            // Topic-level opt-out
            if (method_exists($notification, 'getCategory')) {
                $category = $notification->getCategory();
                $topicEnabled = $preferences?->{$category} ?? true;
                if (!$topicEnabled) {
                    return;
                }
            }

            // 2. Prevent duplicate delivery within the same request lifecycle
            $idempotencyKey = $this->resolveNotificationIdempotencyKey($user, $notification);
            if ($this->isDispatchedInCurrentRequest($idempotencyKey)) {
                Log::debug('Suppressing duplicate Web Push delivery for notification in same request', [
                    'user_id' => $user->id,
                    'key' => $idempotencyKey,
                ]);
                return;
            }
            $this->recordDispatchInCurrentRequest($idempotencyKey);

            // 3. Fetch active device subscriptions with bounded fan-out limit
            $maxDevices = (int) config('webpush.max_devices_per_delivery', 10);
            $rawSubscriptions = $user->deviceSubscriptions()
                ->active()
                ->latest('last_used_at')
                ->limit($maxDevices)
                ->get();

            if ($rawSubscriptions->isEmpty()) {
                return;
            }

            // Pre-flight endpoint safety check: revoke unsafe devices immediately
            $subscriptions = collect();
            foreach ($rawSubscriptions as $device) {
                if (!$this->isSafeDeliveryEndpoint($device->endpoint)) {
                    $device->revoke();
                    continue;
                }
                $subscriptions->push($device);
            }

            if ($subscriptions->isEmpty()) {
                return;
            }

            // 4. Format privacy-safe payload
            $payload = WebPushPayloadFormatter::format($notification, $user);
            $payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            // 5. Handle test faking mode
            if (self::isFaking()) {
                self::$fakeDispatches[] = [
                    'user_id' => $user->id,
                    'user_uuid' => $user->uuid,
                    'notification_class' => get_class($notification),
                    'payload' => $payload,
                    'devices_count' => $subscriptions->count(),
                    'subscriptions' => $subscriptions->pluck('uuid')->all(),
                    'timestamp' => now()->toISOString(),
                ];

                // Update last_used_at on faked subscriptions
                foreach ($subscriptions as $sub) {
                    $sub->update(['last_used_at' => now()]);
                }
                return;
            }

            // 6. Validate VAPID configuration
            $publicKey = config('webpush.vapid.public_key');
            $privateKey = config('webpush.vapid.private_key');
            $subject = config('webpush.vapid.subject', 'mailto:admin@sfumsa.ca');

            if (empty($publicKey) || empty($privateKey)) {
                Log::debug('Web Push delivery skipped: VAPID keys not configured in environment.');
                return;
            }

            // 7. Initialize WebPush client with hardened HTTP transport
            $auth = [
                'VAPID' => [
                    'subject' => $subject,
                    'publicKey' => $publicKey,
                    'privateKey' => $privateKey,
                ],
            ];

            $timeout = $this->getDeliveryTimeout();
            $connectTimeout = (float) config('webpush.connect_timeout', 3.0);

            $webPush = $this->resolveWebPushClient($auth, [
                'timeout' => $timeout,
                'connect_timeout' => $connectTimeout,
            ]);

            // 8. Queue push requests for each active device subscription
            $subscriptionMap = [];
            foreach ($subscriptions as $device) {
                $subObj = Subscription::create([
                    'endpoint' => $device->endpoint,
                    'publicKey' => $device->public_key,
                    'authToken' => $device->auth_token,
                    'contentEncoding' => $device->content_encoding ?: 'aes128gcm',
                ]);

                $webPush->queueNotification($subObj, $payloadJson);
                $subscriptionMap[$device->endpoint] = $device;
            }

            // 9. Execute synchronous delivery and evaluate outcomes independently
            foreach ($webPush->flush() as $report) {
                $endpoint = (string) $report->getEndpoint();
                /** @var DeviceSubscription|null $device */
                $device = $subscriptionMap[$endpoint] ?? null;

                if ($report->isSuccess()) {
                    if ($device) {
                        $device->update(['last_used_at' => now()]);
                    }
                } else {
                    // Check if subscription has permanently expired (HTTP 404 or 410)
                    if ($report->isSubscriptionExpired()) {
                        if ($device) {
                            $device->revoke();
                        }
                    } else {
                        // Transient provider error: log safely without leaking secrets or endpoints
                        Log::warning('Web Push delivery provider non-success', [
                            'correlation_id' => (string) Str::uuid(),
                            'user_id' => $user->id,
                            'device_id' => $device?->uuid,
                            'reason' => Str::limit($report->getReason(), 200),
                        ]);
                    }
                }
            }
        } catch (\Throwable $e) {
            // Absolute failure isolation: never let push delivery error bubble up to business transaction
            Log::warning('Web Push delivery failure suppressed', [
                'correlation_id' => (string) Str::uuid(),
                'user_id' => $user->id,
                'error_class' => class_basename($e),
                'message' => Str::limit($e->getMessage(), 150),
            ]);
        }
    }

    /**
     * Resolve WebPush client instance with hardened Guzzle HTTP transport.
     */
    protected function resolveWebPushClient(array $auth, array $options): WebPush
    {
        if (app()->bound(WebPush::class)) {
            return app(WebPush::class);
        }

        $timeout = $this->getDeliveryTimeout();
        $connectTimeout = (float) config('webpush.connect_timeout', 3.0);

        // Hardened HTTP client: enforce connection/response timeouts and strictly defuse redirect SSRF
        $httpClient = new GuzzleClient([
            'timeout' => $timeout,
            'connect_timeout' => $connectTimeout,
            'allow_redirects' => false,
            'http_errors' => false,
        ]);

        return new WebPush($auth, $options, $httpClient);
    }

    /**
     * Validate endpoint safety prior to HTTP delivery.
     */
    protected function isSafeDeliveryEndpoint(string $endpoint): bool
    {
        return PushEndpointValidator::validate($endpoint);
    }

    /**
     * Compute a stable idempotency key for this notification dispatch.
     * Prevents unintended repeated delivery of the same logical notification within one request,
     * while ensuring distinct legitimate notifications for the same user are delivered independently.
     */
    protected function resolveNotificationIdempotencyKey(User $user, Notification $notification): string
    {
        $uniqueId = null;

        if (method_exists($notification, 'idempotencyKey')) {
            $uniqueId = (string) $notification->idempotencyKey();
        } elseif (!empty($notification->id)) {
            $uniqueId = (string) $notification->id;
        } elseif (property_exists($notification, 'message') && !empty($notification->message?->uuid)) {
            $uniqueId = (string) $notification->message->uuid;
        } elseif (property_exists($notification, 'inquiry') && !empty($notification->inquiry?->uuid)) {
            $subKey = property_exists($notification, 'newStatus') ? ':' . $notification->newStatus : '';
            $uniqueId = (string) $notification->inquiry->uuid . $subKey . ':' . spl_object_hash($notification);
        } else {
            $uniqueId = spl_object_hash($notification);
        }

        return get_class($notification) . ':' . $user->id . ':' . $uniqueId;
    }

    protected function isWebPushMasterEnabled(): bool
    {
        try {
            if (class_exists(\App\Http\Controllers\Api\V1\Admin\ConnectSystemController::class)) {
                $config = \App\Http\Controllers\Api\V1\Admin\ConnectSystemController::getPersistedConfig();
                if (isset($config['webpush_enabled'])) {
                    return (bool) $config['webpush_enabled'];
                }
            }
        } catch (\Throwable) {
        }

        return true;
    }

    protected function getDeliveryTimeout(): float
    {
        try {
            if (class_exists(\App\Http\Controllers\Api\V1\Admin\ConnectSystemController::class)) {
                $config = \App\Http\Controllers\Api\V1\Admin\ConnectSystemController::getPersistedConfig();
                if (isset($config['delivery_timeout_seconds'])) {
                    return min(30.0, max(1.0, (float) $config['delivery_timeout_seconds']));
                }
            }
        } catch (\Throwable) {
        }

        return (float) config('webpush.timeout', 5.0);
    }
}
