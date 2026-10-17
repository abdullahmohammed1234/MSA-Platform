<?php

namespace Tests\Feature\Security;

use App\Models\User;
use App\Store\Enums\ProductStatus;
use App\Store\Models\StoreCart;
use App\Store\Models\StoreProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestCartIsolationTest extends TestCase
{
    use RefreshDatabase;

    private const GUEST_A = 'a1b2c3d4-0000-4000-8000-00000000000a';
    private const GUEST_B = 'b1b2c3d4-0000-4000-8000-00000000000b';

    private StoreProduct $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->product = StoreProduct::create([
            'name' => 'MSA Hoodie',
            'price_cents' => 4500,
            'status' => ProductStatus::Active,
            'inventory_quantity' => 20,
        ]);
    }

    private function asGuest(string $sessionId): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withHeaders(['X-Session-ID' => $sessionId]);
    }

    private function addToCart(string $sessionId, int $quantity = 2): string
    {
        return $this->asGuest($sessionId)
            ->postJson('/api/v1/store/cart/items', ['product_id' => $this->product->id, 'quantity' => $quantity])
            ->assertOk()
            ->json('data.items.0.uuid');
    }

    public function test_one_guest_cannot_read_or_change_another_guests_cart(): void
    {
        $itemUuid = $this->addToCart(self::GUEST_A);

        $this->asGuest(self::GUEST_B)->getJson('/api/v1/store/cart')
            ->assertOk()
            ->assertJsonPath('data.item_count', 0);

        $this->asGuest(self::GUEST_B)
            ->patchJson("/api/v1/store/cart/items/{$itemUuid}", ['quantity' => 9])
            ->assertNotFound();

        $this->asGuest(self::GUEST_B)->deleteJson("/api/v1/store/cart/items/{$itemUuid}")->assertOk();
        $this->asGuest(self::GUEST_B)->deleteJson('/api/v1/store/cart')->assertOk();

        $this->asGuest(self::GUEST_A)->getJson('/api/v1/store/cart')
            ->assertOk()
            ->assertJsonPath('data.item_count', 2);
    }

    public function test_guest_presenting_a_signed_in_users_session_id_does_not_get_their_cart(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withHeaders(['X-Session-ID' => self::GUEST_A])
            ->postJson('/api/v1/store/cart/items', ['product_id' => $this->product->id, 'quantity' => 3])
            ->assertOk();

        $this->asGuest(self::GUEST_A)->getJson('/api/v1/store/cart')
            ->assertOk()
            ->assertJsonPath('data.item_count', 0);

        $this->asGuest(self::GUEST_A)->deleteJson('/api/v1/store/cart')->assertOk();

        $this->assertSame(3, (int) StoreCart::where('user_id', $user->id)->firstOrFail()->items()->sum('quantity'));
    }

    public function test_guest_cannot_check_out_a_signed_in_users_cart(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withHeaders(['X-Session-ID' => self::GUEST_A])
            ->postJson('/api/v1/store/cart/items', ['product_id' => $this->product->id, 'quantity' => 3])
            ->assertOk();

        $this->asGuest(self::GUEST_A)
            ->postJson('/api/v1/store/checkout', [
                'customer_name' => 'Not The Owner',
                'customer_email' => 'attacker@example.com',
                'customer_phone' => '604-555-0199',
            ])
            ->assertStatus(422);

        $this->assertSame(20, $this->product->fresh()->inventory_quantity);
    }

    public function test_short_guessable_session_ids_are_not_honoured(): void
    {
        $shared = StoreCart::create(['session_id' => '1', 'expires_at' => now()->addDay()]);
        $shared->items()->create(['product_id' => $this->product->id, 'quantity' => 4]);

        $this->asGuest('1')->getJson('/api/v1/store/cart')
            ->assertOk()
            ->assertJsonPath('data.item_count', 0);
    }

    public function test_signed_in_user_keeps_their_own_cart(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/v1/store/cart/items', ['product_id' => $this->product->id, 'quantity' => 1])
            ->assertOk();

        $this->actingAs($user)->getJson('/api/v1/store/cart')
            ->assertOk()
            ->assertJsonPath('data.item_count', 1);
    }
}
