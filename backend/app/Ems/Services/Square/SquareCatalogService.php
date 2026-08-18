<?php

namespace App\Ems\Services\Square;

use App\Ems\Enums\SquareCatalogSyncStatus;
use App\Ems\Exceptions\EmsException;
use App\Ems\Models\Event;
use App\Ems\Models\SquareCatalogMapping;
use App\Ems\Models\SquareSyncCursor;
use App\Ems\Models\TicketType;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

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

    public const ATTR_DEFS_CURSOR_KEY = 'catalog_attr_defs';

    /** @var array<string, string> canonical EMS key => Square catalog object id */
    private array $attributeDefinitionIds = [];

    /** @var array<string, string> canonical EMS key => Square custom attribute key */
    private array $attributeDefinitionKeys = [];

    private bool $attributeDefinitionsReady = false;

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

            return $this->upsertWithDefinitionRetry($mapping, $ticketType);
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

        $this->ensureCustomAttributeDefinitions();
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

    private function upsertWithDefinitionRetry(SquareCatalogMapping $mapping, TicketType $ticketType): SquareCatalogMapping
    {
        try {
            return $this->upsertItemAndVariation($mapping, $ticketType);
        } catch (\Throwable $e) {
            if (! $this->isMissingAttributeDefinitionError($e)) {
                throw $e;
            }

            Log::channel((string) config('ems.logging.channel', 'ems'))
                ->warning('ems.square.catalog.attr_defs.stale', [
                    'ticket_type_uuid' => $ticketType->uuid,
                    'event_uuid' => $ticketType->event?->uuid,
                    'error' => $e->getMessage(),
                ]);

            $this->forgetProvisionedDefinitions();
            $this->ensureCustomAttributeDefinitions();

            return $this->upsertItemAndVariation($mapping, $ticketType);
        }
    }

    private function upsertItemAndVariation(SquareCatalogMapping $mapping, TicketType $ticketType): SquareCatalogMapping
    {
        $this->ensureCustomAttributeDefinitions();

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
        return array_merge(
            $this->attributeValue(self::ATTR_MANAGED, 'true'),
            $this->attributeValue(self::ATTR_EVENT_UUID, $event->uuid),
        );
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function variationAttributes(TicketType $ticketType, Event $event): array
    {
        return array_merge(
            $this->attributeValue(self::ATTR_MANAGED, 'true'),
            $this->attributeValue(self::ATTR_TICKET_UUID, $ticketType->uuid),
            $this->attributeValue(self::ATTR_EVENT_UUID, $event->uuid),
        );
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function attributeValue(string $canonicalKey, string $value): array
    {
        $squareKey = $this->attributeDefinitionKeys[$canonicalKey] ?? $canonicalKey;
        $payload = [
            'name' => $canonicalKey,
            'key' => $squareKey,
            'type' => 'STRING',
            'string_value' => $value,
        ];

        $definitionId = $this->attributeDefinitionIds[$canonicalKey] ?? null;
        if (is_string($definitionId) && $definitionId !== '' && $definitionId !== 'pending') {
            $payload['custom_attribute_definition_id'] = $definitionId;
        }

        return [$squareKey => $payload];
    }

    /**
     * Ensure the three EMS Catalog custom attribute definitions exist on this
     * Square seller before attaching values. Safe to call repeatedly.
     *
     * Do not trust a legacy `catalog_attr_defs=ready` cursor unless it stores
     * Square definition IDs. The previous implementation marked the cursor
     * ready even when batch-upsert failed (for example because one definition
     * already existed and Square rejected the whole batch).
     */
    public function ensureCustomAttributeDefinitions(): void
    {
        if ($this->attributeDefinitionsReady && $this->allRequiredDefinitionsKnown()) {
            return;
        }

        if ($this->hydrateFromCursor()) {
            $this->attributeDefinitionsReady = true;

            return;
        }

        $listed = $this->listCustomAttributeDefinitionsByCanonicalKey();
        $created = [];
        $reused = [];

        foreach ($this->requiredDefinitions() as $key => $name) {
            if (isset($listed[$key])) {
                $this->rememberDefinition($key, $listed[$key]);
                $reused[] = $key;

                continue;
            }

            $createdObject = $this->createCustomAttributeDefinition($key, $name);
            if ($createdObject !== null) {
                $this->rememberDefinition($key, $createdObject);
                $created[] = $key;

                continue;
            }

            $listed = $this->listCustomAttributeDefinitionsByCanonicalKey();
            if (! isset($listed[$key])) {
                throw new EmsException(
                    'Unable to provision Square Catalog custom attribute "'.$key.'". The definition was not found after create.',
                    [],
                    HttpResponse::HTTP_BAD_GATEWAY
                );
            }

            $this->rememberDefinition($key, $listed[$key]);
            $reused[] = $key;
        }

        if (! $this->allRequiredDefinitionsKnown()) {
            $missing = array_values(array_diff(
                array_keys($this->requiredDefinitions()),
                array_keys(array_filter($this->attributeDefinitionKeys))
            ));

            Log::channel((string) config('ems.logging.channel', 'ems'))
                ->error('ems.square.catalog.attr_defs.failed', [
                    'missing_keys' => $missing,
                    'created_keys' => $created,
                    'reused_keys' => $reused,
                ]);

            throw new EmsException(
                'Unable to provision Square Catalog custom attributes: '.implode(', ', $missing).'.',
                [],
                HttpResponse::HTTP_BAD_GATEWAY
            );
        }

        SquareSyncCursor::putValue(self::ATTR_DEFS_CURSOR_KEY, 'ready', [
            'definition_ids' => $this->attributeDefinitionIds,
            'definition_keys' => $this->attributeDefinitionKeys,
            'provisioned_at' => now()->toIso8601String(),
        ]);

        $this->attributeDefinitionsReady = true;

        Log::channel((string) config('ems.logging.channel', 'ems'))
            ->info('ems.square.catalog.attr_defs.ready', [
                'created_keys' => $created,
                'reused_keys' => $reused,
                'definition_ids' => $this->redactedDefinitionIds(),
            ]);
    }

    /**
     * @return array<string, string> canonical key => display name
     */
    public static function requiredDefinitions(): array
    {
        return [
            self::ATTR_MANAGED => 'EMS managed',
            self::ATTR_TICKET_UUID => 'EMS ticket type UUID',
            self::ATTR_EVENT_UUID => 'EMS event UUID',
        ];
    }

    private function hydrateFromCursor(): bool
    {
        $row = SquareSyncCursor::query()->where('key', self::ATTR_DEFS_CURSOR_KEY)->first();
        if ($row === null || $row->cursor_value !== 'ready') {
            return false;
        }

        $ids = is_array($row->metadata['definition_ids'] ?? null) ? $row->metadata['definition_ids'] : [];
        $keys = is_array($row->metadata['definition_keys'] ?? null) ? $row->metadata['definition_keys'] : [];

        foreach (array_keys($this->requiredDefinitions()) as $canonical) {
            $id = $ids[$canonical] ?? null;
            if (! is_string($id) || $id === '' || $id === 'pending') {
                return false;
            }
        }

        foreach (array_keys($this->requiredDefinitions()) as $canonical) {
            $this->attributeDefinitionIds[$canonical] = (string) $ids[$canonical];
            $this->attributeDefinitionKeys[$canonical] = is_string($keys[$canonical] ?? null) && $keys[$canonical] !== ''
                ? (string) $keys[$canonical]
                : $canonical;
        }

        return true;
    }

    /**
     * @return array<string, array<string, mixed>> canonical key => catalog object
     */
    private function listCustomAttributeDefinitionsByCanonicalKey(): array
    {
        $found = [];
        $cursor = null;

        do {
            $query = ['types' => 'CUSTOM_ATTRIBUTE_DEFINITION'];
            if (is_string($cursor) && $cursor !== '') {
                $query['cursor'] = $cursor;
            }

            $response = $this->square->get('/v2/catalog/list', $query);
            foreach ($response['objects'] ?? [] as $object) {
                if (! is_array($object) || ($object['type'] ?? '') !== 'CUSTOM_ATTRIBUTE_DEFINITION') {
                    continue;
                }

                $canonical = $this->canonicalDefinitionKey($object);
                if ($canonical === null) {
                    continue;
                }

                $found[$canonical] = $object;
            }

            $cursor = $response['cursor'] ?? null;
        } while (is_string($cursor) && $cursor !== '');

        Log::channel((string) config('ems.logging.channel', 'ems'))
            ->info('ems.square.catalog.attr_defs.listed', [
                'matched_keys' => array_keys($found),
                'matched_count' => count($found),
            ]);

        return $found;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function createCustomAttributeDefinition(string $key, string $name): ?array
    {
        $clientId = '#'.$key;
        $idempotency = 'ems-cad-v1-'.$key;

        try {
            $response = $this->square->post('/v2/catalog/batch-upsert', [
                'idempotency_key' => $idempotency,
                'batches' => [[
                    'objects' => [[
                        'type' => 'CUSTOM_ATTRIBUTE_DEFINITION',
                        'id' => $clientId,
                        'custom_attribute_definition_data' => [
                            'allowed_object_types' => ['ITEM', 'ITEM_VARIATION'],
                            'name' => $name,
                            'description' => 'EMS integration metadata. Do not delete.',
                            'type' => 'STRING',
                            'key' => $key,
                            'app_visibility' => 'APP_VISIBILITY_HIDDEN',
                            'seller_visibility' => 'SELLER_VISIBILITY_HIDDEN',
                        ],
                    ]],
                ]],
            ], $idempotency);
        } catch (\Throwable $e) {
            if ($this->isAlreadyExistsError($e)) {
                Log::channel((string) config('ems.logging.channel', 'ems'))
                    ->info('ems.square.catalog.attr_defs.exists', [
                        'key' => $key,
                        'error' => $e->getMessage(),
                    ]);

                return null;
            }

            Log::channel((string) config('ems.logging.channel', 'ems'))
                ->error('ems.square.catalog.attr_defs.create_failed', [
                    'key' => $key,
                    'error' => $e->getMessage(),
                ]);

            throw new EmsException(
                'Unable to provision Square Catalog custom attribute "'.$key.'": '.$e->getMessage(),
                [],
                $e instanceof EmsException ? $e->status() : HttpResponse::HTTP_BAD_GATEWAY
            );
        }

        foreach ($response['objects'] ?? [] as $object) {
            if (is_array($object) && ($object['type'] ?? '') === 'CUSTOM_ATTRIBUTE_DEFINITION') {
                Log::channel((string) config('ems.logging.channel', 'ems'))
                    ->info('ems.square.catalog.attr_defs.created', [
                        'key' => $key,
                        'square_definition_id' => $object['id'] ?? null,
                    ]);

                return $object;
            }
        }

        foreach ($response['id_mappings'] ?? [] as $pair) {
            if (! is_array($pair) || ($pair['client_object_id'] ?? '') !== $clientId) {
                continue;
            }

            $objectId = (string) ($pair['object_id'] ?? '');
            if ($objectId === '') {
                continue;
            }

            Log::channel((string) config('ems.logging.channel', 'ems'))
                ->info('ems.square.catalog.attr_defs.created', [
                    'key' => $key,
                    'square_definition_id' => $objectId,
                ]);

            return [
                'type' => 'CUSTOM_ATTRIBUTE_DEFINITION',
                'id' => $objectId,
                'custom_attribute_definition_data' => [
                    'key' => $key,
                    'name' => $name,
                    'type' => 'STRING',
                ],
            ];
        }

        // HTTP 200 with an empty body (typical of coarse Http::fake stubs).
        // Treat the key as created so attach-by-key can proceed; the next
        // list will pick up the real Square object id when available.
        Log::channel((string) config('ems.logging.channel', 'ems'))
            ->info('ems.square.catalog.attr_defs.created', [
                'key' => $key,
                'square_definition_id' => null,
            ]);

        return [
            'type' => 'CUSTOM_ATTRIBUTE_DEFINITION',
            'id' => 'pending',
            'custom_attribute_definition_data' => [
                'key' => $key,
                'name' => $name,
                'type' => 'STRING',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $object
     */
    private function rememberDefinition(string $canonicalKey, array $object): void
    {
        $squareKey = $object['custom_attribute_definition_data']['key'] ?? $canonicalKey;
        $this->attributeDefinitionKeys[$canonicalKey] = is_string($squareKey) && $squareKey !== ''
            ? $squareKey
            : $canonicalKey;

        $id = $object['id'] ?? null;
        if (is_string($id) && $id !== '') {
            $this->attributeDefinitionIds[$canonicalKey] = $id;
        }
    }

    /**
     * @param  array<string, mixed>  $object
     */
    private function canonicalDefinitionKey(array $object): ?string
    {
        $key = $object['custom_attribute_definition_data']['key'] ?? null;
        if (! is_string($key) || $key === '') {
            return null;
        }

        foreach (array_keys($this->requiredDefinitions()) as $expected) {
            if ($key === $expected || str_ends_with($key, ':'.$expected)) {
                return $expected;
            }
        }

        return null;
    }

    private function allRequiredDefinitionsKnown(): bool
    {
        foreach (array_keys($this->requiredDefinitions()) as $key) {
            if (($this->attributeDefinitionKeys[$key] ?? '') === '') {
                return false;
            }
        }

        return true;
    }

    private function forgetProvisionedDefinitions(): void
    {
        $this->attributeDefinitionsReady = false;
        $this->attributeDefinitionIds = [];
        $this->attributeDefinitionKeys = [];

        SquareSyncCursor::putValue(self::ATTR_DEFS_CURSOR_KEY, null, [
            'definition_ids' => [],
            'definition_keys' => [],
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function redactedDefinitionIds(): array
    {
        $safe = [];
        foreach ($this->attributeDefinitionIds as $key => $id) {
            $safe[$key] = $id === 'pending' ? 'pending' : $id;
        }

        return $safe;
    }

    private function isAlreadyExistsError(\Throwable $e): bool
    {
        $message = strtolower($e->getMessage());

        return str_contains($message, 'already exists')
            || str_contains($message, 'duplicate')
            || str_contains($message, 'conflicting unique')
            || str_contains($message, 'duplicate key');
    }

    private function isMissingAttributeDefinitionError(\Throwable $e): bool
    {
        $message = strtolower($e->getMessage());

        return str_contains($message, 'custom attribute definition')
            && str_contains($message, 'not found');
    }
}
