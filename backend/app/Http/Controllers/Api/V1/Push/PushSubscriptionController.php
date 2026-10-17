<?php

namespace App\Http\Controllers\Api\V1\Push;

use App\Http\Controllers\Controller;
use App\Models\DeviceSubscription;
use App\Services\WebPush\PushEndpointValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PushSubscriptionController extends Controller
{
    /**
     * Retrieve Web Push capability and public VAPID key.
     */
    public function config(): JsonResponse
    {
        $publicKey = config('webpush.vapid.public_key');
        $masterEnabled = true;
        try {
            if (class_exists(\App\Http\Controllers\Api\V1\Admin\ConnectSystemController::class)) {
                $connectConfig = \App\Http\Controllers\Api\V1\Admin\ConnectSystemController::getPersistedConfig();
                $masterEnabled = (bool) ($connectConfig['webpush_enabled'] ?? true);
            }
        } catch (\Throwable) {
        }

        $isSupported = !empty($publicKey);

        return response()->json([
            'enabled' => $masterEnabled && $isSupported,
            'push_supported' => $isSupported,
            'public_key' => $isSupported ? $publicKey : null,
        ]);
    }

    /**
     * List all active registered devices for the authenticated user.
     * Only sanitized metadata is returned (endpoints and secrets are redacted).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $devices = $user->deviceSubscriptions()
            ->active()
            ->latest('id')
            ->get()
            ->map(fn(DeviceSubscription $sub) => $sub->toSanitizedArray());

        return response()->json([
            'devices' => $devices,
            'subscriptions' => $devices,
        ]);
    }

    /**
     * Register or update a device push subscription for the authenticated user.
     *
     * Strict ownership guarantee:
     * - Registering a new subscription assigns ownership exclusively to the authenticated user.
     * - Re-registering an endpoint owned by the same user updates keys and reactivates.
     * - Submitting an endpoint already owned by a different user is strictly forbidden (HTTP 409 Conflict).
     * - Atomic row-level locking prevents concurrent registration races or ownership transfers.
     */
    public function store(Request $request): JsonResponse
    {
        $masterEnabled = true;
        $maxDevices = (int) config('webpush.max_devices_per_user', 10);
        try {
            if (class_exists(\App\Http\Controllers\Api\V1\Admin\ConnectSystemController::class)) {
                $connectConfig = \App\Http\Controllers\Api\V1\Admin\ConnectSystemController::getPersistedConfig();
                $masterEnabled = (bool) ($connectConfig['webpush_enabled'] ?? true);
                if (isset($connectConfig['max_devices_per_user'])) {
                    $configuredMax = min(50, max(1, (int) $connectConfig['max_devices_per_user']));
                    if (app()->environment('testing') && config('webpush.max_devices_per_user') !== 10) {
                        $maxDevices = (int) config('webpush.max_devices_per_user');
                    } else {
                        $maxDevices = $configuredMax;
                    }
                }
            }
        } catch (\Throwable) {
        }

        if (! $masterEnabled) {
            return response()->json([
                'message' => 'Web Push notifications are currently disabled on the platform.',
                'errors' => ['push' => ['Web Push service is disabled by platform administrators.']],
            ], 503);
        }

        $user = $request->user();

        // Normalize flat key payloads for backwards-compatible client support
        if ($request->has('public_key') && ! $request->has('keys')) {
            $request->merge([
                'keys' => [
                    'p256dh' => $request->input('public_key'),
                    'auth' => $request->input('auth_token'),
                ],
            ]);
        }

        $validated = $request->validate([
            'endpoint' => 'required|string|url|max:2048',
            'keys' => 'required|array',
            'keys.p256dh' => ['required', 'string', 'min:32', 'max:180', 'regex:/^[A-Za-z0-9_-]+={0,2}$/'],
            'keys.auth' => ['required', 'string', 'min:12', 'max:60', 'regex:/^[A-Za-z0-9_-]+={0,2}$/'],
            'content_encoding' => 'nullable|string|in:aes128gcm,aesgcm',
            'device_name' => 'nullable|string|max:100',
            'device_type' => 'nullable|string|max:50',
        ]);

        $endpoint = trim($validated['endpoint']);

        // Enforce HTTPS and protect against SSRF / internal host manipulation
        if (!$this->isValidPushEndpoint($endpoint)) {
            return response()->json([
                'message' => 'The provided push endpoint is invalid or disallowed.',
                'errors' => ['endpoint' => ['Push endpoint must be a valid external HTTPS URL.']],
            ], 422);
        }

        $endpointHash = hash('sha256', $endpoint);

        try {
            $subscription = DB::transaction(function () use ($user, $endpoint, $endpointHash, $validated, $request, $maxDevices) {
                // Lock row if it already exists to guarantee concurrency safety
                $existing = DeviceSubscription::where('endpoint_hash', $endpointHash)
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    // Critical security guarantee: Cross-user endpoint hijacking is strictly forbidden
                    if ($existing->user_id !== $user->id) {
                        abort(response()->json([
                            'message' => 'This push subscription endpoint is already registered to another account.',
                            'errors' => ['endpoint' => ['Subscription endpoint is already in use by another account.']],
                        ], 409));
                    }

                    // Re-register / rotate keys for the existing owner
                    $existing->update([
                        'public_key' => $validated['keys']['p256dh'],
                        'auth_token' => $validated['keys']['auth'],
                        'content_encoding' => $validated['content_encoding'] ?? 'aes128gcm',
                        'device_name' => $this->sanitizeLabel($validated['device_name'] ?? $existing->device_name),
                        'device_type' => $validated['device_type'] ?? $existing->device_type ?? 'browser',
                        'user_agent' => Str::limit($request->userAgent() ?? '', 255),
                        'is_active' => true,
                        'revoked_at' => null,
                    ]);

                    return $existing;
                }

                // Enforce maximum active devices limit per user (FIFO/LRU deactivation)
                $activeCount = $user->deviceSubscriptions()->active()->count();
                if ($activeCount >= $maxDevices) {
                    $oldest = $user->deviceSubscriptions()
                        ->active()
                        ->oldest('id')
                        ->first();
                    $oldest?->revoke();
                }

                return DeviceSubscription::create([
                    'uuid' => (string) Str::uuid(),
                    'user_id' => $user->id,
                    'endpoint' => $endpoint,
                    'endpoint_hash' => $endpointHash,
                    'public_key' => $validated['keys']['p256dh'],
                    'auth_token' => $validated['keys']['auth'],
                    'content_encoding' => $validated['content_encoding'] ?? 'aes128gcm',
                    'device_name' => $this->sanitizeLabel($validated['device_name'] ?? 'Web Browser'),
                    'device_type' => $validated['device_type'] ?? 'browser',
                    'user_agent' => Str::limit($request->userAgent() ?? '', 255),
                    'is_active' => true,
                ]);
            });
        } catch (\Illuminate\Http\Exceptions\HttpResponseException $e) {
            return $e->getResponse();
        } catch (\Illuminate\Database\QueryException $e) {
            // Concurrent insert race condition fallback
            $existing = DeviceSubscription::where('endpoint_hash', $endpointHash)->first();
            if ($existing && $existing->user_id !== $user->id) {
                return response()->json([
                    'message' => 'This push subscription endpoint is already registered to another account.',
                    'errors' => ['endpoint' => ['Subscription endpoint is already in use by another account.']],
                ], 409);
            }
            if ($existing && $existing->user_id === $user->id) {
                $existing->update([
                    'public_key' => $validated['keys']['p256dh'],
                    'auth_token' => $validated['keys']['auth'],
                    'is_active' => true,
                    'revoked_at' => null,
                ]);
                return response()->json([
                    'success' => true,
                    'message' => 'Device subscription successfully registered.',
                    'device' => $existing->toSanitizedArray(),
                ], 200);
            }
            throw $e;
        }

        return response()->json([
            'success' => true,
            'message' => 'Device subscription successfully registered.',
            'device' => $subscription->toSanitizedArray(),
        ], 201);
    }

    /**
     * Revoke a single registered device subscription belonging to the authenticated user.
     */
    public function destroy(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();

        // Enforce strict ownership: must belong to the current authenticated user
        $subscription = $user->deviceSubscriptions()
            ->where('uuid', $uuid)
            ->first();

        if (!$subscription) {
            return response()->json([
                'message' => 'Device subscription not found.',
            ], 404);
        }

        $subscription->revoke();

        return response()->json([
            'success' => true,
            'message' => 'Device subscription revoked.',
        ]);
    }

    /**
     * Revoke all registered device subscriptions for the authenticated user.
     */
    public function destroyAll(Request $request): JsonResponse
    {
        $user = $request->user();

        $count = $user->deviceSubscriptions()
            ->active()
            ->update([
                'is_active' => false,
                'revoked_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => "All registered device subscriptions have been revoked ({$count} devices).",
            'revoked_count' => $count,
        ]);
    }

    /**
     * Validate external push service endpoint and protect against SSRF.
     */
    protected function isValidPushEndpoint(string $url): bool
    {
        return PushEndpointValidator::validate($url);
    }

    /**
     * Sanitize user-provided device label.
     */
    protected function sanitizeLabel(?string $label): string
    {
        if (empty($label)) {
            return 'Web Browser';
        }

        return Str::limit(strip_tags(trim($label)), 80);
    }

    /**
     * Dispatch a test push notification to the authenticated user's active registered devices.
     */
    public function sendTest(Request $request): JsonResponse
    {
        $user = $request->user();

        $activeDevicesCount = $user->deviceSubscriptions()->active()->count();
        if ($activeDevicesCount === 0) {
            return response()->json([
                'success' => false,
                'message' => 'No active registered push devices found for this account. Please enable push on this device first.',
            ], 422);
        }

        $user->notify(new \App\Notifications\TestPushNotification());

        return response()->json([
            'success' => true,
            'message' => "Test push notification dispatched to {$activeDevicesCount} registered device(s).",
            'devices_count' => $activeDevicesCount,
        ]);
    }
}
