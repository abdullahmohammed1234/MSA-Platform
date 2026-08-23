<?php

namespace Tests\Feature\Ems;

use App\Ems\Enums\EventStatus;
use App\Ems\Enums\SquareCatalogSyncStatus;
use App\Ems\Jobs\SyncTicketTypeToSquareJob;
use App\Ems\Models\Event;
use App\Ems\Models\SquareCatalogMapping;
use App\Ems\Models\TicketType;
use App\Ems\Services\Square\SquareCatalogService;
use App\Ems\Support\EmsRoles;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\Http;

class EmsSquareCatalogIdempotencyTest extends EmsTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ems.payments.enabled' => true,
            'ems.payments.square.access_token' => 'test-token',
            'ems.payments.square.location_id' => 'LOCATION_TEST',
            'ems.payments.square.application_id' => 'app-test',
            'ems.payments.square.environment' => 'sandbox',
            'queue.default' => 'sync',
        ]);
    }

    public function test_same_payload_retry_reuses_idempotency_key(): void
    {
        $batches = [[
            'objects' => [[
                'type' => 'ITEM_VARIATION',
                'id' => 'VAR_1',
                'item_variation_data' => ['name' => 'GA', 'price_money' => ['amount' => 1500]],
            ]],
        ]];

        $uuid = '29a88ff3-4b47-4a70-aad8-df103846eb3e';
        $first = SquareCatalogService::idempotencyKeyForUpsert($uuid, $batches);
        $second = SquareCatalogService::idempotencyKeyForUpsert($uuid, $batches);

        $this->assertSame($first, $second);
        $this->assertStringStartsWith('ems-cat-' . $uuid . '-', $first);
    }

    public function test_changed_payload_gets_new_idempotency_key(): void
    {
        $uuid = '29a88ff3-4b47-4a70-aad8-df103846eb3e';
        $base = [[
            'objects' => [[
                'type' => 'ITEM_VARIATION',
                'id' => 'VAR_1',
                'item_variation_data' => ['name' => 'GA', 'price_money' => ['amount' => 1500]],
            ]],
        ]];
        $changed = [[
            'objects' => [[
                'type' => 'ITEM_VARIATION',
                'id' => 'VAR_1',
                'item_variation_data' => ['name' => 'GA', 'price_money' => ['amount' => 2000]],
            ]],
        ]];

        $this->assertNotSame(
            SquareCatalogService::idempotencyKeyForUpsert($uuid, $base),
            SquareCatalogService::idempotencyKeyForUpsert($uuid, $changed)
        );
    }

    public function test_two_catalog_mutations_use_different_keys(): void
    {
        $a = SquareCatalogService::idempotencyKeyForUpsert('tt-a', [[
            'objects' => [['type' => 'ITEM_VARIATION', 'id' => '#a']],
        ]]);
        $b = SquareCatalogService::idempotencyKeyForUpsert('tt-b', [[
            'objects' => [['type' => 'ITEM_VARIATION', 'id' => '#b']],
        ]]);

        $this->assertNotSame($a, $b);
    }

    public function test_attribute_definition_retry_does_not_reuse_key_for_different_payload(): void
    {
        $event = $this->openEvent(['name' => 'Frosh']);
        $ticket = TicketType::factory()->paid(15)->create([
            'event_id' => $event->id,
            'name' => 'GA',
            'uuid' => '29a88ff3-4b47-4a70-aad8-df103846eb3e',
        ]);

        $upsertKeys = [];
        $itemUpsertCount = 0;
        $provisionedKeys = [];
        $publishDefinitions = false;

        Http::fake(function (\Illuminate\Http\Client\Request $request) use (
            &$upsertKeys,
            &$itemUpsertCount,
            &$provisionedKeys,
            &$publishDefinitions,
            $event,
            $ticket
        ) {
            $url = $request->url();

            if ($request->method() === 'GET' && str_contains($url, '/v2/catalog/list')) {
                if (! $publishDefinitions) {
                    return Http::response(['objects' => []], 200);
                }

                $objects = [];
                foreach ($provisionedKeys as $key) {
                    $objects[] = [
                        'type' => 'CUSTOM_ATTRIBUTE_DEFINITION',
                        'id' => 'CAD_' . $key,
                        'custom_attribute_definition_data' => [
                            'key' => $key,
                            'name' => $key,
                            'type' => 'STRING',
                        ],
                    ];
                }

                return Http::response(['objects' => $objects], 200);
            }

            if (! str_contains($url, '/v2/catalog/batch-upsert')) {
                return Http::response(['objects' => []], 200);
            }

            $payload = $request->data();
            if ($payload === []) {
                $decoded = json_decode($request->body(), true);
                $payload = is_array($decoded) ? $decoded : [];
            }

            $objects = $payload['batches'][0]['objects'] ?? [];
            $type = $objects[0]['type'] ?? '';

            if ($type === 'CUSTOM_ATTRIBUTE_DEFINITION') {
                $key = (string) ($objects[0]['custom_attribute_definition_data']['key'] ?? '');
                $provisionedKeys[$key] = $key;

                // Empty body → service treats definition id as "pending", so the
                // first ticket upsert omits custom_attribute_definition_id.
                return Http::response(['objects' => [], 'id_mappings' => []], 200);
            }

            $itemUpsertCount++;
            $upsertKeys[] = (string) ($payload['idempotency_key'] ?? '');

            if ($itemUpsertCount === 1) {
                $publishDefinitions = true;

                return Http::response([
                    'errors' => [[
                        'category' => 'INVALID_REQUEST_ERROR',
                        'code' => 'NOT_FOUND',
                        'detail' => 'Custom attribute definition not found.',
                    ]],
                ], 400);
            }

            // Second attempt includes definition ids → different body/key.
            $this->assertNotEmpty(
                $objects[0]['item_data']['variations'][0]['custom_attribute_values']
                    ?? $objects[0]['custom_attribute_values']
                    ?? []
            );

            return Http::response([
                'objects' => [[
                    'type' => 'ITEM',
                    'id' => 'ITEM_1',
                    'version' => 2,
                    'item_data' => [
                        'variations' => [[
                            'type' => 'ITEM_VARIATION',
                            'id' => 'VAR_1',
                            'version' => 3,
                        ]],
                    ],
                ]],
                'id_mappings' => [
                    ['client_object_id' => '#ems-item-' . $event->uuid, 'object_id' => 'ITEM_1'],
                    ['client_object_id' => '#ems-var-' . $ticket->uuid, 'object_id' => 'VAR_1'],
                ],
            ], 200);
        });

        $mapping = app(SquareCatalogService::class)->syncTicketType($ticket);

        $this->assertSame(SquareCatalogSyncStatus::Synced, $mapping->sync_status);
        $this->assertGreaterThanOrEqual(2, $itemUpsertCount);
        $this->assertCount(2, $upsertKeys);
        $this->assertNotSame($upsertKeys[0], $upsertKeys[1], 'Changed payload after attr-def retry must use a new idempotency key');
    }

    public function test_concurrent_syncs_with_identical_payload_share_one_key(): void
    {
        $event = $this->openEvent();
        $ticket = TicketType::factory()->paid(15)->create([
            'event_id' => $event->id,
            'name' => 'GA',
        ]);
        SquareCatalogMapping::query()->create([
            'event_id' => $event->id,
            'ticket_type_id' => $ticket->id,
            'square_catalog_item_id' => 'ITEM_EXISTING',
            'square_catalog_variation_id' => 'VAR_EXISTING',
            'catalog_variation_version' => 4,
            'sync_status' => SquareCatalogSyncStatus::Synced->value,
            'ems_managed' => true,
        ]);

        $keys = [];
        Http::fake(function (\Illuminate\Http\Client\Request $request) use (&$keys) {
            $url = $request->url();

            if ($request->method() === 'GET' && str_contains($url, '/v2/catalog/list')) {
                return Http::response(['objects' => $this->definitionObjects()], 200);
            }

            if (str_contains($url, '/v2/catalog/object/')) {
                return Http::response([
                    'object' => [
                        'id' => 'VAR_EXISTING',
                        'version' => 4,
                        'type' => 'ITEM_VARIATION',
                        'item_variation_data' => ['item_id' => 'ITEM_EXISTING'],
                    ],
                ], 200);
            }

            if (str_contains($url, '/v2/catalog/batch-upsert')) {
                $payload = $request->data() ?: (json_decode($request->body(), true) ?: []);
                $objects = $payload['batches'][0]['objects'] ?? [];
                if (($objects[0]['type'] ?? '') === 'CUSTOM_ATTRIBUTE_DEFINITION') {
                    return Http::response(['objects' => []], 200);
                }

                $keys[] = (string) ($payload['idempotency_key'] ?? '');

                return Http::response([
                    'objects' => [[
                        'type' => 'ITEM_VARIATION',
                        'id' => 'VAR_EXISTING',
                        'version' => 5,
                        'item_variation_data' => ['item_id' => 'ITEM_EXISTING'],
                    ]],
                ], 200);
            }

            return Http::response(['objects' => []], 200);
        });

        $catalog = app(SquareCatalogService::class);
        $catalog->syncTicketType($ticket->fresh(['event']));
        $catalog->syncTicketType($ticket->fresh(['event']));

        $this->assertCount(2, $keys);
        $this->assertSame($keys[0], $keys[1]);
    }

    public function test_queue_retry_after_payload_change_does_not_reuse_stale_key(): void
    {
        $event = $this->openEvent();
        $ticket = TicketType::factory()->paid(15)->create([
            'event_id' => $event->id,
            'name' => 'GA',
        ]);
        SquareCatalogMapping::query()->create([
            'event_id' => $event->id,
            'ticket_type_id' => $ticket->id,
            'square_catalog_item_id' => 'ITEM_EXISTING',
            'square_catalog_variation_id' => 'VAR_EXISTING',
            'catalog_variation_version' => 4,
            'sync_status' => SquareCatalogSyncStatus::Synced->value,
            'ems_managed' => true,
        ]);

        $keys = [];
        $attempts = 0;

        Http::fake(function (\Illuminate\Http\Client\Request $request) use (&$keys, &$attempts, $ticket) {
            $url = $request->url();

            if ($request->method() === 'GET' && str_contains($url, '/v2/catalog/list')) {
                return Http::response(['objects' => $this->definitionObjects()], 200);
            }

            if (str_contains($url, '/v2/catalog/object/')) {
                return Http::response([
                    'object' => [
                        'id' => 'VAR_EXISTING',
                        'version' => 4 + $attempts,
                        'type' => 'ITEM_VARIATION',
                        'item_variation_data' => ['item_id' => 'ITEM_EXISTING'],
                    ],
                ], 200);
            }

            if (str_contains($url, '/v2/catalog/batch-upsert')) {
                $payload = $request->data() ?: (json_decode($request->body(), true) ?: []);
                $objects = $payload['batches'][0]['objects'] ?? [];
                if (($objects[0]['type'] ?? '') === 'CUSTOM_ATTRIBUTE_DEFINITION') {
                    return Http::response(['objects' => []], 200);
                }

                $attempts++;
                $keys[] = (string) ($payload['idempotency_key'] ?? '');

                if ($attempts === 1) {
                    // Catalog state changes before the next attempt (queue retry /
                    // re-dispatch after a transient Square failure).
                    $ticket->price = 25;
                    $ticket->save();

                    return Http::response([
                        'errors' => [[
                            'category' => 'API_ERROR',
                            'code' => 'INTERNAL_SERVER_ERROR',
                            'detail' => 'temporary failure',
                        ]],
                    ], 500);
                }

                return Http::response([
                    'objects' => [[
                        'type' => 'ITEM_VARIATION',
                        'id' => 'VAR_EXISTING',
                        'version' => 6,
                        'item_variation_data' => ['item_id' => 'ITEM_EXISTING'],
                    ]],
                ], 200);
            }

            return Http::response(['objects' => []], 200);
        });

        $job = new SyncTicketTypeToSquareJob($ticket->id);
        // syncTicketType records Failed and does not rethrow; a later job run
        // (re-dispatch / worker retry of a different failure mode) must not
        // reuse the prior idempotency key once the payload changed.
        $job->handle(app(SquareCatalogService::class));
        $job->handle(app(SquareCatalogService::class));

        $this->assertCount(2, $keys);
        $this->assertNotSame($keys[0], $keys[1]);
        $this->assertSame(SquareCatalogSyncStatus::Synced, SquareCatalogMapping::query()->first()->sync_status);
    }

    public function test_sync_job_is_unique_per_ticket_type(): void
    {
        $jobA = new SyncTicketTypeToSquareJob(42, false);
        $jobB = new SyncTicketTypeToSquareJob(42, true);
        $jobC = new SyncTicketTypeToSquareJob(43);

        $this->assertSame('ems-sq-cat-42', $jobA->uniqueId());
        $this->assertSame('ems-sq-cat-42', $jobB->uniqueId());
        $this->assertSame('ems-sq-cat-43', $jobC->uniqueId());
        $this->assertInstanceOf(ShouldBeUnique::class, $jobA);
    }

    public function test_catalog_mapping_behavior_remains_intact_after_idempotency_fix(): void
    {
        $event = $this->openEvent(['name' => 'Gala']);
        $user = $this->emsUser(EmsRoles::EVENT_ADMINISTRATOR);

        Http::fake(function (\Illuminate\Http\Client\Request $request) use ($event) {
            if ($request->method() === 'GET' && str_contains($request->url(), '/v2/catalog/list')) {
                return Http::response(['objects' => $this->definitionObjects()], 200);
            }

            if (str_contains($request->url(), '/v2/catalog/batch-upsert')) {
                $payload = $request->data() ?: (json_decode($request->body(), true) ?: []);
                $objects = $payload['batches'][0]['objects'] ?? [];
                if (($objects[0]['type'] ?? '') === 'CUSTOM_ATTRIBUTE_DEFINITION') {
                    return Http::response(['objects' => []], 200);
                }

                $this->assertNotEmpty($payload['idempotency_key'] ?? null);
                $this->assertStringStartsWith('ems-cat-', (string) $payload['idempotency_key']);

                return Http::response([
                    'objects' => [[
                        'type' => 'ITEM',
                        'id' => 'ITEM_GALA',
                        'version' => 1,
                        'item_data' => [
                            'variations' => [[
                                'type' => 'ITEM_VARIATION',
                                'id' => 'VAR_GALA',
                                'version' => 1,
                            ]],
                        ],
                    ]],
                    'id_mappings' => [
                        ['client_object_id' => '#ems-item-' . $event->uuid, 'object_id' => 'ITEM_GALA'],
                    ],
                ], 200);
            }

            return Http::response(['objects' => []], 200);
        });

        $this->actingAsEms($user)->postJson($this->url("events/{$event->uuid}/tickets"), [
            'name' => 'VIP',
            'price' => 40,
            'currency' => 'CAD',
        ])->assertCreated();

        $ticket = TicketType::query()->where('event_id', $event->id)->firstOrFail();
        $mapping = SquareCatalogMapping::query()->where('ticket_type_id', $ticket->id)->first();

        $this->assertNotNull($mapping);
        $this->assertSame('ITEM_GALA', $mapping->square_catalog_item_id);
        $this->assertSame('VAR_GALA', $mapping->square_catalog_variation_id);
        $this->assertSame(SquareCatalogSyncStatus::Synced, $mapping->sync_status);
    }

    public function test_version_change_alone_rotates_idempotency_key(): void
    {
        $uuid = 'd4bd055a-bb9f-40de-a7e6-62ac4943b347';
        $v4 = [[
            'objects' => [[
                'type' => 'ITEM_VARIATION',
                'id' => 'VAR',
                'version' => 4,
                'item_variation_data' => ['name' => 'GA'],
            ]],
        ]];
        $v5 = [[
            'objects' => [[
                'type' => 'ITEM_VARIATION',
                'id' => 'VAR',
                'version' => 5,
                'item_variation_data' => ['name' => 'GA'],
            ]],
        ]];

        // Old buggy key ignored version; new strategy must not.
        $this->assertNotSame(
            SquareCatalogService::idempotencyKeyForUpsert($uuid, $v4),
            SquareCatalogService::idempotencyKeyForUpsert($uuid, $v5)
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function definitionObjects(): array
    {
        $out = [];
        foreach (array_keys(SquareCatalogService::requiredDefinitions()) as $key) {
            $out[] = [
                'type' => 'CUSTOM_ATTRIBUTE_DEFINITION',
                'id' => 'CAD_' . $key,
                'custom_attribute_definition_data' => [
                    'key' => $key,
                    'name' => $key,
                    'type' => 'STRING',
                ],
            ];
        }

        return $out;
    }

    private function openEvent(array $attributes = []): Event
    {
        $category = $this->category(['is_active' => true]);

        return Event::factory()->publiclyDiscoverable()->create(array_merge([
            'category_id' => $category->id,
            'capacity' => 100,
            'status' => EventStatus::RegistrationOpen,
        ], $attributes));
    }
}
