<?php

namespace App\Ems\Services\Square;

use App\Ems\Enums\SquareCatalogSyncStatus;
use App\Ems\Enums\WebhookEventStatus;
use App\Ems\Models\SquareCatalogMapping;
use App\Ems\Models\SquareSyncCursor;
use App\Ems\Models\WebhookEvent;
use Illuminate\Support\Facades\Cache;

class SquareHealthService
{
    public function __construct(private readonly SquareClient $square)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        $enabled = (bool) config('ems.payments.enabled', false);
        $tokenConfigured = (string) config('ems.payments.square.access_token', '') !== '';
        $locationConfigured = $this->square->locationId() !== '';
        $applicationIdConfigured = (string) config('ems.payments.square.application_id', '') !== '';
        $webhookConfigured = (string) config('ems.payments.square.webhook_signature_key', '') !== '';
        $terminalDevice = (string) config('ems.payments.square.terminal_device_id', '');

        $probes = $enabled && $tokenConfigured && $locationConfigured
            ? Cache::remember('ems.square.health.probes', 120, fn () => $this->probeApis())
            : [
                'catalog' => 'not_probed',
                'orders' => 'not_probed',
                'payments' => 'not_probed',
                'refunds' => 'not_probed',
            ];

        $lastSync = SquareCatalogMapping::query()->max('last_synced_at');
        $cursor = SquareSyncCursor::query()->where('key', 'payments_reconcile')->first();

        return [
            'status' => $enabled && $tokenConfigured && $locationConfigured ? 'Connected' : 'Disconnected',
            'enabled' => $enabled,
            'environment' => $this->square->environment(),
            'mode' => $this->square->environment(),
            'location_configured' => $locationConfigured,
            'credentials_configured' => $tokenConfigured && $applicationIdConfigured,
            'authentication' => $tokenConfigured ? 'Valid Credentials' : 'Missing Credentials',
            'api_availability' => $probes['payments'] === 'ok' ? 'Operational' : ($enabled ? 'Unknown' : 'Disabled'),
            'catalog_api' => $probes['catalog'],
            'orders_api' => $probes['orders'],
            'payments_api' => $probes['payments'],
            'refunds_api' => $probes['refunds'],
            'webhook_configuration' => $webhookConfigured ? 'Connected' : 'Missing Signature Key',
            'webhook_connectivity' => $webhookConfigured ? 'Connected' : 'Missing Signature Key',
            'webhook_notification_url' => (string) config('ems.payments.square.webhook_notification_url'),
            'terminal_availability' => $terminalDevice !== '' ? 'configured' : 'not_configured',
            'last_successful_synchronization' => $lastSync,
            'unmatched_transactions' => WebhookEvent::query()
                ->where('provider', 'square')
                ->where('status', WebhookEventStatus::Unmatched->value)
                ->count(),
            'failed_sync_jobs' => SquareCatalogMapping::query()
                ->where('sync_status', SquareCatalogSyncStatus::Failed->value)
                ->count(),
            'last_reconciliation_cursor' => $cursor?->cursor_value,
        ];
    }

    /**
     * @return array{catalog: string, orders: string, payments: string, refunds: string}
     */
    private function probeApis(): array
    {
        return [
            'catalog' => $this->square->ping('/v2/catalog/info') ? 'ok' : 'error',
            'orders' => $this->probeOrders(),
            'payments' => $this->square->ping('/v2/payments?location_id=' . urlencode($this->square->locationId()) . '&limit=1') ? 'ok' : 'error',
            'refunds' => $this->square->ping('/v2/refunds?location_id=' . urlencode($this->square->locationId()) . '&limit=1') ? 'ok' : 'error',
        ];
    }

    private function probeOrders(): string
    {
        try {
            $this->square->post('/v2/orders/search', [
                'location_ids' => [$this->square->locationId()],
                'limit' => 1,
            ]);

            return 'ok';
        } catch (\Throwable) {
            return 'error';
        }
    }
}
