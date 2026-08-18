<?php

namespace App\Ems\Services\Square;

use App\Ems\Enums\SquareCatalogSyncStatus;
use App\Ems\Models\Event;
use App\Ems\Models\SquareCatalogMapping;
use App\Ems\Models\SquareSyncCursor;
use App\Ems\Models\TicketType;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Bidirectional EMS ticket type ↔ Square Catalog mapping.
 *
 * Authority: EMS owns ticket identity, price, and availability for
 * ems_managed items. Square-side edits are detected as conflicts and
 * surfaced; "Refresh from Square" applies them explicitly.
 *
 * Unrelated Square merchandise is never imported unless an admin maps it.
 */
class SquareCatalogService
{
    public const ATTR_MANAGED = 'ems_managed';

    public const ATTR_TICKET_UUID = 'ems_ticket_type_uuid';

    public const ATTR_EVENT_UUID = 'ems_event_uuid';

    public function __construct(private readonly SquareClient $square)
    {
    }

    public function syncTicketType(TicketType $ticketType, bool $archive = false): SquareCatalogMapping
    {
        $ticketType->loadMissing('event');
        $mapping = $this->mappingFor($ticketType);

        if (! $this->square->enabled()) {
            $mapping->sync_status = SquareCatalogSyncStatus::NotSynced;
            $mapping->last_error = 'Square payments are not enabled.';
            $mapping->save();

            return $mapping;
        }

        if ((float) $ticketType->price <= 0 && ! $mapping->square_catalog_variation_id) {
            $mapping->sync_status = SquareCatalogSyncStatus::NotSynced;
            $mapping->last_error = 'Free ticket types are not published to Square Catalog.';
            $mapping->save();

            return $mapping;
        }

        try {
            $this->ensureCustomAttributeDefinitions();

            if ($archive || ! $ticketType->is_active) {
                return $this->archiveVariation($mapping, $ticketType);
            }

            return $this->upsertItemAndVariation($mapping, $ticketType);
        } catch (\Throwable $e) {
            $mapping->sync_status = SquareCatalogSyncStatus::Failed;
            $mapping->last_error = Str::limit($e->getMessage(), 500, '');
            $mapping->retry_count = (int) $mapping->retry_count + 1;
            $mapping->save();

            Log::channel((string) config('ems.logging.channel', 'ems'))
                ->error('ems.square.catalog.sync_failed', [
                    'ticket_type_uuid' => $ticketType->uuid,
                    'event_uuid' => $ticketType->event?->uuid,
                    'error' => $e->getMessage(),
                ]);

            return $mapping;
        }
    }

    public function pullRemoteChanges(): int
    {
        if (! $this->square->enabled()) {
            return 0;
        }

        $begin = SquareSyncCursor::getValue('catalog_search');
        $payload = [
            'object_types' => ['ITEM', 'ITEM_VARIATION'],
            'include_deleted_objects' => true,
            'include_related_objects' => true,
        ];
        if ($begin) {
            $payload['begin_time'] = $begin;
        }

        $updated = 0;
        $cursor = null;

        do {
            if ($cursor) {
                $payload['cursor'] = $cursor;
            }

            $response = $this->square->post('/v2/catalog/search', $payload);
            $objects = $response['objects'] ?? [];
            foreach ($objects as $object) {
                if ($this->applyRemoteObject(is_array($object) ? $object : [])) {
                    $updated++;
                }
            }

            $cursor = $response['cursor'] ?? null;
            if (! empty($response['latest_time'])) {
                SquareSyncCursor::putValue('catalog_search', (string) $response['latest_time']);
            }
        } while (is_string($cursor) && $cursor !== '');

        Log::channel((string) config('ems.logging.channel', 'ems'))
            ->info('ems.square.catalog.pull', ['updated' => $updated]);

        return $updated;
    }

    /**
     * Explicit import: map an existing Square variation onto an EMS ticket type.
     */
    public function importVariation(TicketType $ticketType, string $variationId): SquareCatalogMapping
    {
        $object = $this->square->get('/v2/catalog/object/' . urlencode($variationId), [
            'include_related_objects' => 'true',
        ]);

        $variation = $object['object'] ?? [];
        if (($variation['type'] ?? '') !== 'ITEM_VARIATION') {
            throw new \InvalidArgumentException('Square object is not an item variation.');
        }

        $mapping = $this->mappingFor($ticketType);
        $mapping->square_catalog_variation_id = (string) $variation['id'];
        $mapping->square_catalog_item_id = (string) ($variation['item_variation_data']['item_id'] ?? '');
        $mapping->catalog_variation_version = isset($variation['version']) ? (int) $variation['version'] : null;
        $mapping->square_location_id = $this->square->locationId();
        $mapping->ems_managed = true;
        $mapping->sync_status = SquareCatalogSyncStatus::Synced;
        $mapping->last_synced_at = now();
        $mapping->last_error = null;
        $mapping->save();

        $this->pushEmsMetadata($mapping, $ticketType);

        Log::channel((string) config('ems.logging.channel', 'ems'))
            ->info('ems.square.catalog.imported', [
                'ticket_type_uuid' => $ticketType->uuid,
                'square_variation_id' => $mapping->square_catalog_variation_id,
            ]);

        return $mapping->fresh();
    }

    /**
     * Apply Square as the source for name/price on an EMS-managed mapping.
     */
    public function refreshFromSquare(TicketType $ticketType): SquareCatalogMapping
    {
        $mapping = $this->mappingFor($ticketType);
        if (! $mapping->square_catalog_variation_id) {
            return $this->syncTicketType($ticketType);
        }

        $object = $this->square->get('/v2/catalog/object/' . urlencode($mapping->square_catalog_variation_id));
        $variation = $object['object'] ?? [];
        $data = $variation['item_variation_data'] ?? [];

        if (isset($data['name']) && is_string($data['name']) && $data['name'] !== '') {
            $ticketType->name = $data['name'];
        }
        if (isset($data['price_money']['amount'])) {
            $ticketType->price = number_format(((int) $data['price_money']['amount']) / 100, 2, '.', '');
        }
        $ticketType->save();

        $mapping->catalog_variation_version = isset($variation['version']) ? (int) $variation['version'] : $mapping->catalog_variation_version;
        $mapping->sync_status = SquareCatalogSyncStatus::Synced;
        $mapping->last_conflict_at = null;
        $mapping->last_conflict_summary = null;
        $mapping->last_synced_at = now();
        $mapping->last_error = null;
        $mapping->save();

        return $mapping->fresh();
    }

    public function mappingFor(TicketType $ticketType): SquareCatalogMapping
    {
        $existing = SquareCatalogMapping::query()->where('ticket_type_id', $ticketType->id)->first();
        if ($existing) {
            return $existing;
        }

        $mapping = new SquareCatalogMapping();
        $mapping->event_id = $ticketType->event_id;
        $mapping->ticket_type_id = $ticketType->id;
        $mapping->square_location_id = $this->square->locationId() ?: null;
        $mapping->sync_status = SquareCatalogSyncStatus::Pending;
        $mapping->ems_managed = true;
        $mapping->save();

        return $mapping;
    }

    public function findByVariationId(string $variationId): ?SquareCatalogMapping
    {
        return SquareCatalogMapping::query()
            ->where('square_catalog_variation_id', $variationId)
            ->with(['ticketType', 'event'])
            ->first();
    }

    private function upsertItemAndVariation(SquareCatalogMapping $mapping, TicketType $ticketType): SquareCatalogMapping
    {
        $event = $ticketType->event;
        $itemId = $mapping->square_catalog_item_id ?: $this->existingItemIdForEvent($event);

        $itemClientId = $itemId ?: '#ems-item-' . $event->uuid;
        $varClientId = $mapping->square_catalog_variation_id ?: '#ems-var-' . $ticketType->uuid;

        $objects = [];

        if (! $itemId) {
            $objects[] = [
                'type' => 'ITEM',
                'id' => $itemClientId,
                'present_at_all_locations' => true,
                'custom_attribute_values' => $this->itemAttributes($event),
                'item_data' => [
                    'name' => $event->name,
                    'description' => 'EMS event tickets — ' . $event->name,
                    'product_type' => 'REGULAR',
                    'variations' => [
                        $this->variationObject($varClientId, $itemClientId, $ticketType, $event, null),
                    ],
                ],
            ];
        } else {
            $existingVar = null;
            $existingVersion = $mapping->catalog_variation_version;
            if ($mapping->square_catalog_variation_id) {
                try {
                    $retrieved = $this->square->get('/v2/catalog/object/' . urlencode($mapping->square_catalog_variation_id));
                    $existingVar = $retrieved['object'] ?? null;
                    $existingVersion = isset($existingVar['version']) ? (int) $existingVar['version'] : $existingVersion;
                } catch (\Throwable) {
                    $existingVar = null;
                }
            }

            $objects[] = $this->variationObject(
                $mapping->square_catalog_variation_id ?: $varClientId,
                $itemId,
                $ticketType,
                $event,
                $existingVersion
            );
        }

        $idempotency = 'ems-cat-' . $ticketType->uuid . '-' . substr(sha1(
            $ticketType->name . '|' . $ticketType->price . '|' . ($ticketType->is_active ? '1' : '0') . '|' . ($mapping->square_catalog_variation_id ?? '')
        ), 0, 12);

        $response = $this->square->post('/v2/catalog/batch-upsert', [
            'idempotency_key' => $idempotency,
            'batches' => [['objects' => $objects]],
        ], $idempotency);

        $idMap = [];
        foreach ($response['id_mappings'] ?? [] as $pair) {
            if (isset($pair['client_object_id'], $pair['object_id'])) {
                $idMap[$pair['client_object_id']] = $pair['object_id'];
            }
        }

        $resolvedItem = $idMap[$itemClientId] ?? $itemId;
        $resolvedVar = $idMap[$varClientId]
            ?? $idMap[$mapping->square_catalog_variation_id ?? '']
            ?? $mapping->square_catalog_variation_id;

        foreach ($response['objects'] ?? [] as $obj) {
            if (($obj['type'] ?? '') === 'ITEM' && ! empty($obj['id'])) {
                $resolvedItem = $obj['id'];
                $mapping->catalog_item_version = isset($obj['version']) ? (int) $obj['version'] : $mapping->catalog_item_version;
                foreach ($obj['item_data']['variations'] ?? [] as $v) {
                    if (! empty($v['id'])) {
                        $resolvedVar = $v['id'];
                        $mapping->catalog_variation_version = isset($v['version']) ? (int) $v['version'] : $mapping->catalog_variation_version;
                    }
                }
            }
            if (($obj['type'] ?? '') === 'ITEM_VARIATION' && ! empty($obj['id'])) {
                $resolvedVar = $obj['id'];
                $mapping->catalog_variation_version = isset($obj['version']) ? (int) $obj['version'] : $mapping->catalog_variation_version;
                $resolvedItem = $obj['item_variation_data']['item_id'] ?? $resolvedItem;
            }
        }

        $mapping->square_catalog_item_id = $resolvedItem;
        $mapping->square_catalog_variation_id = $resolvedVar;
        $mapping->square_location_id = $this->square->locationId();
        $mapping->sync_status = SquareCatalogSyncStatus::Synced;
        $mapping->last_synced_at = now();
        $mapping->last_error = null;
        $mapping->last_conflict_at = null;
        $mapping->last_conflict_summary = null;
        $mapping->save();

        Log::channel((string) config('ems.logging.channel', 'ems'))
            ->info('ems.square.catalog.synced', [
                'ticket_type_uuid' => $ticketType->uuid,
                'event_uuid' => $event->uuid,
                'square_item_id' => $mapping->square_catalog_item_id,
                'square_variation_id' => $mapping->square_catalog_variation_id,
            ]);

        return $mapping;
    }

    /**
     * @return array<string, mixed>
     */
    private function variationObject(
        string $id,
        string $itemId,
        TicketType $ticketType,
        Event $event,
        ?int $version
    ): array {
        $object = [
            'type' => 'ITEM_VARIATION',
            'id' => $id,
            'present_at_all_locations' => true,
            'custom_attribute_values' => $this->variationAttributes($ticketType, $event),
            'item_variation_data' => [
                'item_id' => $itemId,
                'name' => $ticketType->name,
                'sku' => 'ems:' . $ticketType->uuid,
                'pricing_type' => 'FIXED_PRICING',
                'price_money' => [
                    'amount' => (int) round(((float) $ticketType->price) * 100),
                    'currency' => strtoupper($ticketType->currency ?: 'CAD'),
                ],
                'sellable' => (bool) $ticketType->is_active,
                'track_inventory' => false,
            ],
        ];

        if ($version) {
            $object['version'] = $version;
        }

        return $object;
    }

    private function archiveVariation(SquareCatalogMapping $mapping, TicketType $ticketType): SquareCatalogMapping
    {
        if (! $mapping->square_catalog_variation_id) {
            $mapping->sync_status = SquareCatalogSyncStatus::Archived;
            $mapping->last_synced_at = now();
            $mapping->save();

            return $mapping;
        }

        $mapping = $this->upsertItemAndVariation($mapping, $ticketType);
        $mapping->sync_status = SquareCatalogSyncStatus::Archived;
        $mapping->save();

        return $mapping;
    }

    private function existingItemIdForEvent(Event $event): ?string
    {
        $row = SquareCatalogMapping::query()
            ->where('event_id', $event->id)
            ->whereNotNull('square_catalog_item_id')
            ->orderBy('id')
            ->first();

        return $row?->square_catalog_item_id;
    }

    private function applyRemoteObject(array $object): bool
    {
        $type = $object['type'] ?? '';
        if ($type !== 'ITEM_VARIATION') {
            return false;
        }

        $variationId = (string) ($object['id'] ?? '');
        if ($variationId === '') {
            return false;
        }

        $mapping = $this->findByVariationId($variationId);
        if ($mapping === null || ! $mapping->ems_managed) {
            return false;
        }

        $ticketType = $mapping->ticketType;
        if ($ticketType === null) {
            return false;
        }

        $data = $object['item_variation_data'] ?? [];
        $remoteName = (string) ($data['name'] ?? '');
        $remoteAmount = isset($data['price_money']['amount'])
            ? number_format(((int) $data['price_money']['amount']) / 100, 2, '.', '')
            : null;

        $conflicts = [];
        if ($remoteName !== '' && $remoteName !== $ticketType->name) {
            $conflicts[] = 'name';
        }
        if ($remoteAmount !== null && (float) $remoteAmount !== (float) $ticketType->price) {
            $conflicts[] = 'price';
        }

        $mapping->catalog_variation_version = isset($object['version']) ? (int) $object['version'] : $mapping->catalog_variation_version;

        if ($conflicts !== []) {
            $mapping->sync_status = SquareCatalogSyncStatus::Conflict;
            $mapping->last_conflict_at = now();
            $mapping->last_conflict_summary = 'Square catalog differs on: ' . implode(', ', $conflicts);
            $mapping->save();

            Log::channel((string) config('ems.logging.channel', 'ems'))
                ->warning('ems.square.catalog.conflict', [
                    'ticket_type_uuid' => $ticketType->uuid,
                    'square_variation_id' => $variationId,
                    'fields' => $conflicts,
                ]);

            return true;
        }

        $deleted = (bool) ($object['is_deleted'] ?? false);
        if ($deleted) {
            $mapping->sync_status = SquareCatalogSyncStatus::Archived;
            $mapping->save();

            return true;
        }

        $mapping->sync_status = SquareCatalogSyncStatus::Synced;
        $mapping->last_synced_at = now();
        $mapping->last_error = null;
        $mapping->save();

        return true;
    }

    private function pushEmsMetadata(SquareCatalogMapping $mapping, TicketType $ticketType): void
    {
        if (! $mapping->square_catalog_variation_id) {
            return;
        }

        $this->upsertItemAndVariation($mapping, $ticketType);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function itemAttributes(Event $event): array
    {
        return [
            self::ATTR_MANAGED => ['name' => self::ATTR_MANAGED, 'key' => self::ATTR_MANAGED, 'type' => 'STRING', 'string_value' => 'true'],
            self::ATTR_EVENT_UUID => ['name' => self::ATTR_EVENT_UUID, 'key' => self::ATTR_EVENT_UUID, 'type' => 'STRING', 'string_value' => $event->uuid],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function variationAttributes(TicketType $ticketType, Event $event): array
    {
        return [
            self::ATTR_MANAGED => ['name' => self::ATTR_MANAGED, 'key' => self::ATTR_MANAGED, 'type' => 'STRING', 'string_value' => 'true'],
            self::ATTR_TICKET_UUID => ['name' => self::ATTR_TICKET_UUID, 'key' => self::ATTR_TICKET_UUID, 'type' => 'STRING', 'string_value' => $ticketType->uuid],
            self::ATTR_EVENT_UUID => ['name' => self::ATTR_EVENT_UUID, 'key' => self::ATTR_EVENT_UUID, 'type' => 'STRING', 'string_value' => $event->uuid],
        ];
    }

    private function ensureCustomAttributeDefinitions(): void
    {
        if (SquareSyncCursor::getValue('catalog_attr_defs') === 'ready') {
            return;
        }

        $defs = [
            ['key' => self::ATTR_MANAGED, 'name' => 'EMS managed'],
            ['key' => self::ATTR_TICKET_UUID, 'name' => 'EMS ticket type UUID'],
            ['key' => self::ATTR_EVENT_UUID, 'name' => 'EMS event UUID'],
        ];

        $objects = [];
        foreach ($defs as $def) {
            $objects[] = [
                'type' => 'CUSTOM_ATTRIBUTE_DEFINITION',
                'id' => '#' . $def['key'],
                'custom_attribute_definition_data' => [
                    'allowed_object_types' => ['ITEM', 'ITEM_VARIATION'],
                    'name' => $def['name'],
                    'type' => 'STRING',
                    'key' => $def['key'],
                    'app_visibility' => 'APP_VISIBILITY_HIDDEN',
                    'seller_visibility' => 'SELLER_VISIBILITY_READ_WRITE_VALUES',
                ],
            ];
        }

        try {
            $this->square->post('/v2/catalog/batch-upsert', [
                'idempotency_key' => 'ems-catalog-attr-defs-v1',
                'batches' => [['objects' => $objects]],
            ], 'ems-catalog-attr-defs-v1');
        } catch (\Throwable $e) {
            // Definitions may already exist from a previous run.
            Log::channel((string) config('ems.logging.channel', 'ems'))
                ->info('ems.square.catalog.attr_defs', ['message' => $e->getMessage()]);
        }

        SquareSyncCursor::putValue('catalog_attr_defs', 'ready');
    }
}
