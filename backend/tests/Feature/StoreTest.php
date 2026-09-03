<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Store\Enums\ProductStatus;
use App\Store\Enums\StoreFulfillmentStatus;
use App\Store\Enums\StorePaymentStatus;
use App\Store\Models\StoreCart;
use App\Store\Models\StoreOrder;
use App\Store\Models\StoreProduct;
use App\Store\Models\StoreProductVariant;
use App\Store\Support\StorePermissions;
use App\Store\Support\StoreRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StoreTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $customerUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles & permissions
        foreach (StorePermissions::definitions() as $name => $def) {
            Permission::firstOrCreate(
                ['slug' => $name],
                [
                    'uuid' => (string) Str::uuid(),
                    'name' => $def['name'],
                    'module' => $def['module'],
                    'description' => $def['description'],
                ]
            );
        }

        $superAdminRole = Role::firstOrCreate(['slug' => 'super-admin'], [
            'uuid' => (string) Str::uuid(),
            'name' => 'Super Admin',
        ]);
        $storeAdminRole = Role::firstOrCreate(['slug' => StoreRoles::STORE_ADMINISTRATOR], [
            'uuid' => (string) Str::uuid(),
            'name' => 'Store Administrator',
        ]);
        $storeAdminRole->permissions()->sync(Permission::all());

        $this->adminUser = User::factory()->create();
        $this->adminUser->roles()->attach($superAdminRole);

        $this->customerUser = User::factory()->create();
    }

    public function test_public_catalogue_returns_only_active_products(): void
    {
        StoreProduct::create([
            'name' => 'SFU MSA Hoodie',
            'price_cents' => 4500,
            'status' => ProductStatus::Active,
            'inventory_quantity' => 10,
        ]);

        StoreProduct::create([
            'name' => 'Draft Hoodie',
            'price_cents' => 4500,
            'status' => ProductStatus::Draft,
            'inventory_quantity' => 5,
        ]);

        $response = $this->getJson('/api/v1/store/products');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'SFU MSA Hoodie');
    }

    public function test_public_product_detail_by_slug(): void
    {
        $product = StoreProduct::create([
            'name' => 'SFU MSA T-Shirt',
            'slug' => 'sfu-msa-t-shirt',
            'price_cents' => 2500,
            'status' => ProductStatus::Active,
            'inventory_quantity' => 20,
        ]);

        $response = $this->getJson('/api/v1/store/products/sfu-msa-t-shirt');

        $response->assertStatus(200);
        $response->assertJsonPath('data.name', 'SFU MSA T-Shirt');
        $response->assertJsonPath('data.formatted_price', '$25.00');
    }

    public function test_cart_add_update_remove_flow(): void
    {
        $product = StoreProduct::create([
            'name' => 'SFU MSA Mug',
            'price_cents' => 1500,
            'status' => ProductStatus::Active,
            'inventory_quantity' => 15,
        ]);

        // Add to cart
        $response = $this->postJson('/api/v1/store/cart/items', [
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.item_count', 2);
        $response->assertJsonPath('data.formatted_subtotal', '$30.00');

        $itemUuid = $response->json('data.items.0.uuid');

        // Update quantity
        $updateRes = $this->patchJson("/api/v1/store/cart/items/{$itemUuid}", [
            'quantity' => 3,
        ]);

        $updateRes->assertStatus(200);
        $updateRes->assertJsonPath('data.item_count', 3);

        // Remove item
        $removeRes = $this->deleteJson("/api/v1/store/cart/items/{$itemUuid}");
        $removeRes->assertStatus(200);
        $removeRes->assertJsonPath('data.item_count', 0);
    }

    public function test_checkout_creates_order_and_reserves_stock(): void
    {
        $product = StoreProduct::create([
            'name' => 'SFU MSA Cap',
            'price_cents' => 2000,
            'status' => ProductStatus::Active,
            'inventory_quantity' => 5,
        ]);

        // Prepare cart
        $cartRes = $this->postJson('/api/v1/store/cart/items', [
            'product_id' => $product->id,
            'quantity' => 2,
        ]);
        $sessionId = $cartRes->headers->get('X-Session-ID');

        // Checkout
        $checkoutRes = $this->postJson('/api/v1/store/checkout', [
            'customer_name' => 'Ahmad Ali',
            'customer_email' => 'ahmad@sfu.ca',
            'customer_phone' => '778-123-4567',
        ]);

        $checkoutRes->assertStatus(201);
        $checkoutRes->assertJsonPath('data.order.customer_name', 'Ahmad Ali');
        $checkoutRes->assertJsonPath('data.order.total_cents', 4000);

        // Verify stock decremented
        $this->assertEquals(3, $product->fresh()->inventory_quantity);
    }

    public function test_admin_can_create_update_and_archive_product(): void
    {
        $this->actingAs($this->adminUser);

        // Create product
        $createRes = $this->postJson('/api/v1/store-admin/products', [
            'name' => 'SFU MSA Winter Jacket',
            'price_cents' => 8500,
            'status' => 'active',
            'inventory_quantity' => 10,
        ]);

        $createRes->assertStatus(201);
        $uuid = $createRes->json('data.uuid');

        // Update product
        $updateRes = $this->putJson("/api/v1/store-admin/products/{$uuid}", [
            'name' => 'SFU MSA Premium Winter Jacket',
            'price_cents' => 9500,
            'status' => 'active',
            'inventory_quantity' => 12,
        ]);

        $updateRes->assertStatus(200);
        $updateRes->assertJsonPath('data.name', 'SFU MSA Premium Winter Jacket');

        // Archive product
        $deleteRes = $this->deleteJson("/api/v1/store-admin/products/{$uuid}");
        $deleteRes->assertStatus(200);
        $this->assertSoftDeleted('store_products', ['uuid' => $uuid]);
    }

    public function test_admin_can_update_fulfillment_and_refund_order(): void
    {
        $this->actingAs($this->adminUser);

        $order = StoreOrder::create([
            'customer_name' => 'Fatima Khan',
            'customer_email' => 'fatima@sfu.ca',
            'subtotal_cents' => 3000,
            'total_cents' => 3000,
            'payment_status' => StorePaymentStatus::Paid,
            'fulfillment_status' => StoreFulfillmentStatus::Pending,
            'paid_at' => now(),
        ]);

        // Update fulfillment
        $fulRes = $this->patchJson("/api/v1/store-admin/orders/{$order->uuid}/fulfillment", [
            'fulfillment_status' => 'ready_for_pickup',
        ]);

        $fulRes->assertStatus(200);
        $fulRes->assertJsonPath('data.fulfillment_status', 'ready_for_pickup');

        // Refund order
        $refRes = $this->postJson("/api/v1/store-admin/orders/{$order->uuid}/refund", [
            'reason' => 'Customer cancellation',
        ]);

        $refRes->assertStatus(200);
        $refRes->assertJsonPath('data.payment_status', 'refunded');
        $refRes->assertJsonPath('data.fulfillment_status', 'cancelled');
    }

    public function test_webhook_marks_store_order_as_paid_and_is_idempotent(): void
    {
        $order = StoreOrder::create([
            'order_number' => 'MS-2026-TEST01',
            'customer_name' => 'Sara Smith',
            'customer_email' => 'sara@sfu.ca',
            'subtotal_cents' => 4500,
            'total_cents' => 4500,
            'payment_status' => StorePaymentStatus::Pending,
            'fulfillment_status' => StoreFulfillmentStatus::Pending,
        ]);

        $payload = [
            'type' => 'payment.updated',
            'event_id' => 'evt_test_store_001',
            'data' => [
                'object' => [
                    'payment' => [
                        'id' => 'sq_pay_123456',
                        'order_id' => 'sq_ord_654321',
                        'reference_id' => 'MS-2026-TEST01',
                        'status' => 'COMPLETED',
                    ],
                ],
            ],
        ];

        $webhookService = app(\App\Ems\Services\SquareWebhookService::class);

        // Process webhook first time
        $record = \App\Ems\Models\WebhookEvent::create([
            'provider' => 'square',
            'event_id' => 'evt_test_store_001',
            'event_type' => 'payment.updated',
            'status' => 'received',
            'payload' => $payload,
        ]);

        $webhookService->processRecord($record);

        $order->refresh();
        $this->assertEquals(StorePaymentStatus::Paid, $order->payment_status);
        $this->assertEquals(StoreFulfillmentStatus::Preparing, $order->fulfillment_status);
        $this->assertEquals('sq_pay_123456', $order->square_payment_id);

        // Duplicate webhook process
        $recordDuplicate = \App\Ems\Models\WebhookEvent::create([
            'provider' => 'square',
            'event_id' => 'evt_test_store_002',
            'event_type' => 'payment.updated',
            'status' => 'received',
            'payload' => $payload,
        ]);

        $webhookService->processRecord($recordDuplicate);

        $order->refresh();
        $this->assertEquals(StorePaymentStatus::Paid, $order->payment_status);
    }

    public function test_expired_pending_order_releases_stock(): void
    {
        $product = StoreProduct::create([
            'name' => 'SFU MSA Mug',
            'price_cents' => 1500,
            'status' => ProductStatus::Active,
            'inventory_quantity' => 10,
        ]);

        $order = StoreOrder::create([
            'customer_name' => 'Adam Vance',
            'customer_email' => 'adam@sfu.ca',
            'subtotal_cents' => 3000,
            'total_cents' => 3000,
            'payment_status' => StorePaymentStatus::Pending,
            'fulfillment_status' => StoreFulfillmentStatus::Pending,
        ]);
        $order->created_at = now()->subMinutes(45);
        $order->save();

        $order->items()->create([
            'product_id' => $product->id,
            'product_name_snapshot' => $product->name,
            'unit_price_cents' => 1500,
            'quantity' => 2,
            'line_total_cents' => 3000,
        ]);

        $checkoutService = app(\App\Store\Services\StoreCheckoutService::class);
        $cleaned = $checkoutService->cleanupExpiredOrders(30);

        $this->assertEquals(1, $cleaned);
        $this->assertEquals(12, $product->fresh()->inventory_quantity);
        $this->assertEquals(StorePaymentStatus::Failed, $order->fresh()->payment_status);
        $this->assertEquals(StoreFulfillmentStatus::Cancelled, $order->fresh()->fulfillment_status);
    }

    public function test_order_refund_restocks_inventory_and_logs_adjustment(): void
    {
        $this->actingAs($this->adminUser);

        $product = StoreProduct::create([
            'name' => 'SFU MSA Sweater',
            'price_cents' => 5000,
            'status' => ProductStatus::Active,
            'inventory_quantity' => 8,
        ]);

        $order = StoreOrder::create([
            'customer_name' => 'Mariam Z',
            'customer_email' => 'mariam@sfu.ca',
            'subtotal_cents' => 5000,
            'total_cents' => 5000,
            'payment_status' => StorePaymentStatus::Paid,
            'fulfillment_status' => StoreFulfillmentStatus::Preparing,
            'paid_at' => now(),
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'product_name_snapshot' => $product->name,
            'unit_price_cents' => 5000,
            'quantity' => 2,
            'line_total_cents' => 10000,
        ]);

        $refRes = $this->postJson("/api/v1/store-admin/orders/{$order->uuid}/refund", [
            'reason' => 'Defective item return',
        ]);

        $refRes->assertStatus(200);
        $this->assertEquals(10, $product->fresh()->inventory_quantity);
        $this->assertDatabaseHas('store_inventory_adjustments', [
            'product_id' => $product->id,
            'adjustment' => 2,
        ]);
    }

    public function test_user_cannot_view_another_users_order(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $orderB = StoreOrder::create([
            'user_id' => $userB->id,
            'customer_name' => 'User B',
            'customer_email' => $userB->email,
            'subtotal_cents' => 2000,
            'total_cents' => 2000,
            'payment_status' => StorePaymentStatus::Paid,
            'fulfillment_status' => StoreFulfillmentStatus::Completed,
        ]);

        $this->actingAs($userA);

        $response = $this->getJson("/api/v1/store/orders/{$orderB->uuid}");
        $response->assertStatus(403);
    }

    public function test_admin_inventory_requires_inventory_permissions(): void
    {
        $nonAdmin = User::factory()->create();
        $this->actingAs($nonAdmin);

        $res1 = $this->getJson('/api/v1/store-admin/inventory');
        $res1->assertStatus(403);

        $res2 = $this->postJson('/api/v1/store-admin/inventory/adjust', [
            'product_id' => 1,
            'new_quantity' => 10,
        ]);
        $res2->assertStatus(403);
    }

    public function test_cart_add_rejects_invalid_quantity(): void
    {
        $product = StoreProduct::create([
            'name' => 'SFU MSA Pin',
            'price_cents' => 500,
            'status' => ProductStatus::Active,
            'inventory_quantity' => 50,
        ]);

        $response = $this->postJson('/api/v1/store/cart/items', [
            'product_id' => $product->id,
            'quantity' => 0,
        ]);

        $response->assertStatus(422);
    }

    public function test_late_payment_capture_after_cleanup_rereserves_stock(): void
    {
        $product = StoreProduct::create([
            'name' => 'SFU MSA Umbrella',
            'price_cents' => 2000,
            'status' => ProductStatus::Active,
            'inventory_quantity' => 10,
        ]);

        $order = StoreOrder::create([
            'order_number' => 'MS-2026-RACE01',
            'customer_name' => 'Late Buyer',
            'customer_email' => 'late@sfu.ca',
            'subtotal_cents' => 4000,
            'total_cents' => 4000,
            'payment_status' => StorePaymentStatus::Pending,
            'fulfillment_status' => StoreFulfillmentStatus::Pending,
        ]);
        $order->created_at = now()->subMinutes(45);
        $order->save();

        $order->items()->create([
            'product_id' => $product->id,
            'product_name_snapshot' => $product->name,
            'unit_price_cents' => 2000,
            'quantity' => 2,
            'line_total_cents' => 4000,
        ]);
        $product->decrement('inventory_quantity', 2); // Initial reservation (stock = 8)

        // 1. Cleanup runs, releases stock (stock = 10, payment_status = Failed)
        $checkoutService = app(\App\Store\Services\StoreCheckoutService::class);
        $checkoutService->cleanupExpiredOrders(30);

        $this->assertEquals(10, $product->fresh()->inventory_quantity);
        $this->assertEquals(StorePaymentStatus::Failed, $order->fresh()->payment_status);

        // 2. Late Square Webhook arrives marking payment paid
        $paymentService = app(\App\Store\Services\StorePaymentService::class);
        $paymentService->markOrderPaid($order, 'sq_pay_late_123');

        // Order is now Paid, stock re-reserved to 8
        $this->assertEquals(StorePaymentStatus::Paid, $order->fresh()->payment_status);
        $this->assertEquals(8, $product->fresh()->inventory_quantity);
        $this->assertDatabaseHas('store_inventory_adjustments', [
            'product_id' => $product->id,
            'adjustment' => -2,
        ]);
    }

    public function test_duplicate_refund_webhook_does_not_restock_twice(): void
    {
        $this->actingAs($this->adminUser);

        $product = StoreProduct::create([
            'name' => 'SFU MSA Scarf',
            'price_cents' => 1500,
            'status' => ProductStatus::Active,
            'inventory_quantity' => 10,
        ]);

        $order = StoreOrder::create([
            'order_number' => 'MS-2026-REFUND01',
            'square_payment_id' => 'sq_pay_refund_dup_999',
            'customer_name' => 'Refund Customer',
            'customer_email' => 'refund@sfu.ca',
            'subtotal_cents' => 3000,
            'total_cents' => 3000,
            'payment_status' => StorePaymentStatus::Paid,
            'fulfillment_status' => StoreFulfillmentStatus::Preparing,
            'paid_at' => now(),
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'product_name_snapshot' => $product->name,
            'unit_price_cents' => 1500,
            'quantity' => 2,
            'line_total_cents' => 3000,
        ]);

        // First refund via admin console
        $paymentService = app(\App\Store\Services\StorePaymentService::class);
        $paymentService->refundOrder($order, 'Customer return');

        $this->assertEquals(12, $product->fresh()->inventory_quantity);
        $this->assertEquals(StorePaymentStatus::Refunded, $order->fresh()->payment_status);

        // Webhook for same refund arrives afterwards
        $webhookService = app(\App\Ems\Services\SquareWebhookService::class);
        $record = \App\Ems\Models\WebhookEvent::create([
            'provider' => 'square',
            'event_id' => 'evt_refund_dup_001',
            'event_type' => 'refund.updated',
            'status' => 'received',
            'payload' => [
                'type' => 'refund.updated',
                'event_id' => 'evt_refund_dup_001',
                'data' => [
                    'object' => [
                        'refund' => [
                            'id' => 'sq_ref_111',
                            'payment_id' => 'sq_pay_refund_dup_999',
                            'status' => 'COMPLETED',
                        ],
                    ],
                ],
            ],
        ]);

        $webhookService->processRecord($record);

        // Stock MUST remain 12, NOT 14
        $this->assertEquals(12, $product->fresh()->inventory_quantity);
        $this->assertEquals(StorePaymentStatus::Refunded, $order->fresh()->payment_status);
        $this->assertEquals('processed', $record->fresh()->status);
    }
}
