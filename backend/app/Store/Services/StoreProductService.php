<?php

namespace App\Store\Services;

use App\Services\Security\AuditLogger;
use App\Store\Enums\ProductStatus;
use App\Store\Models\StoreInventoryAdjustment;
use App\Store\Models\StoreProduct;
use App\Store\Models\StoreProductImage;
use App\Store\Models\StoreProductVariant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StoreProductService
{
    protected AuditLogger $auditLogger;

    public function __construct(AuditLogger $auditLogger)
    {
        $this->auditLogger = $auditLogger;
    }

    /**
     * Public catalogue querying active products only.
     */
    public function listPublicProducts(array $filters = [], int $perPage = 12): LengthAwarePaginator
    {
        $query = StoreProduct::query()
            ->with(['variants' => fn ($q) => $q->where('is_active', true), 'images'])
            ->where('status', ProductStatus::Active);

        if (!empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function (Builder $q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        $sort = strtolower((string) ($filters['sort_by'] ?? 'created_at'));
        $order = strtolower((string) ($filters['sort_order'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

        if ($sort === 'price') {
            $query->orderBy('price_cents', $order);
        } else {
            $query->orderBy('created_at', $order);
        }

        return $query->paginate(min(max($perPage, 1), 50));
    }

    /**
     * Find single active product by slug for public detail page.
     */
    public function getPublicProductBySlug(string $slug): StoreProduct
    {
        return StoreProduct::query()
            ->with(['variants' => fn ($q) => $q->where('is_active', true), 'images'])
            ->where('slug', $slug)
            ->where('status', ProductStatus::Active)
            ->firstOrFail();
    }

    /**
     * Admin list query.
     */
    public function listAdminProducts(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = StoreProduct::query()->with(['variants', 'images']);

        if (!empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function (Builder $q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        return $query->orderBy('created_at', 'desc')->paginate(min(max($perPage, 1), 100));
    }

    /**
     * Create product with optional variants and primary image.
     */
    public function createProduct(array $data, ?int $adminUserId = null): StoreProduct
    {
        return DB::transaction(function () use ($data, $adminUserId) {
            $product = StoreProduct::create([
                'name' => $data['name'],
                'slug' => !empty($data['slug']) ? Str::slug($data['slug']) : Str::slug($data['name']),
                'description' => $data['description'] ?? null,
                'price_cents' => (int) ($data['price_cents'] ?? 0),
                'currency' => $data['currency'] ?? 'CAD',
                'sku' => $data['sku'] ?? null,
                'status' => $data['status'] ?? ProductStatus::Draft->value,
                'has_variants' => !empty($data['has_variants']),
                'inventory_quantity' => (int) ($data['inventory_quantity'] ?? 0),
            ]);

            if (!empty($data['variants']) && is_array($data['variants'])) {
                foreach ($data['variants'] as $v) {
                    $product->variants()->create([
                        'name' => $v['name'],
                        'sku' => $v['sku'] ?? null,
                        'price_override_cents' => isset($v['price_override_cents']) ? (int) $v['price_override_cents'] : null,
                        'inventory_quantity' => (int) ($v['inventory_quantity'] ?? 0),
                        'is_active' => $v['is_active'] ?? true,
                    ]);
                }
            }

            if (!empty($data['image_url'])) {
                $product->images()->create([
                    'image_url' => $data['image_url'],
                    'image_path' => $data['image_path'] ?? null,
                    'display_order' => 0,
                    'is_primary' => true,
                ]);
            }

            $this->auditLogger->log(
                action: 'store.product.created',
                target: $product,
                description: "Created merchandise product: {$product->name}",
                payload: ['name' => $product->name, 'status' => $product->status->value],
                userId: $adminUserId
            );

            return $product->load(['variants', 'images']);
        });
    }

    /**
     * Update product details and sync variants.
     */
    public function updateProduct(StoreProduct $product, array $data, ?int $adminUserId = null): StoreProduct
    {
        return DB::transaction(function () use ($product, $data, $adminUserId) {
            $product->update([
                'name' => $data['name'] ?? $product->name,
                'slug' => !empty($data['slug']) ? Str::slug($data['slug']) : $product->slug,
                'description' => $data['description'] ?? $product->description,
                'price_cents' => isset($data['price_cents']) ? (int) $data['price_cents'] : $product->price_cents,
                'currency' => $data['currency'] ?? $product->currency,
                'sku' => $data['sku'] ?? $product->sku,
                'status' => $data['status'] ?? $product->status,
                'has_variants' => isset($data['has_variants']) ? (bool) $data['has_variants'] : $product->has_variants,
                'inventory_quantity' => isset($data['inventory_quantity']) ? (int) $data['inventory_quantity'] : $product->inventory_quantity,
            ]);

            if (isset($data['variants']) && is_array($data['variants'])) {
                // Upsert variants
                $existingIds = [];
                foreach ($data['variants'] as $v) {
                    if (!empty($v['uuid'])) {
                        $variant = $product->variants()->where('uuid', $v['uuid'])->first();
                        if ($variant) {
                            $variant->update([
                                'name' => $v['name'] ?? $variant->name,
                                'sku' => $v['sku'] ?? $variant->sku,
                                'price_override_cents' => isset($v['price_override_cents']) ? (int) $v['price_override_cents'] : null,
                                'inventory_quantity' => isset($v['inventory_quantity']) ? (int) $v['inventory_quantity'] : $variant->inventory_quantity,
                                'is_active' => $v['is_active'] ?? $variant->is_active,
                            ]);
                            $existingIds[] = $variant->id;
                        }
                    } else {
                        $newVariant = $product->variants()->create([
                            'name' => $v['name'],
                            'sku' => $v['sku'] ?? null,
                            'price_override_cents' => isset($v['price_override_cents']) ? (int) $v['price_override_cents'] : null,
                            'inventory_quantity' => (int) ($v['inventory_quantity'] ?? 0),
                            'is_active' => $v['is_active'] ?? true,
                        ]);
                        $existingIds[] = $newVariant->id;
                    }
                }
            }

            if (!empty($data['image_url'])) {
                $product->images()->delete();
                $product->images()->create([
                    'image_url' => $data['image_url'],
                    'image_path' => $data['image_path'] ?? null,
                    'display_order' => 0,
                    'is_primary' => true,
                ]);
            }

            $this->auditLogger->log(
                action: 'store.product.updated',
                target: $product,
                description: "Updated merchandise product: {$product->name}",
                payload: ['name' => $product->name, 'status' => $product->status->value],
                userId: $adminUserId
            );

            return $product->load(['variants', 'images']);
        });
    }

    /**
     * Perform manual inventory adjustment with audit trail.
     */
    public function adjustInventory(StoreProduct $product, ?StoreProductVariant $variant, int $newQuantity, ?string $reason = null, ?int $adminUserId = null): void
    {
        DB::transaction(function () use ($product, $variant, $newQuantity, $reason, $adminUserId) {
            $prevQty = $variant ? $variant->inventory_quantity : $product->inventory_quantity;
            $adj = $newQuantity - $prevQty;

            if ($variant) {
                $variant->update(['inventory_quantity' => $newQuantity]);
            } else {
                $product->update(['inventory_quantity' => $newQuantity]);
            }

            StoreInventoryAdjustment::create([
                'product_id' => $product->id,
                'variant_id' => $variant?->id,
                'previous_quantity' => $prevQty,
                'new_quantity' => $newQuantity,
                'adjustment' => $adj,
                'reason' => $reason ?? 'Manual Admin Adjustment',
                'user_id' => $adminUserId,
            ]);

            $targetName = $variant ? "{$product->name} ({$variant->name})" : $product->name;

            $this->auditLogger->log(
                action: 'store.inventory.adjusted',
                target: $variant ?? $product,
                description: "Adjusted stock for {$targetName} from {$prevQty} to {$newQuantity} (adj: {$adj})",
                payload: ['previous' => $prevQty, 'new' => $newQuantity, 'reason' => $reason],
                userId: $adminUserId
            );
        });
    }

    /**
     * Soft delete product.
     */
    public function deleteProduct(StoreProduct $product, ?int $adminUserId = null): void
    {
        DB::transaction(function () use ($product, $adminUserId) {
            $product->update(['status' => ProductStatus::Archived]);
            $product->delete();

            $this->auditLogger->log(
                action: 'store.product.archived',
                target: $product,
                description: "Archived product: {$product->name}",
                userId: $adminUserId
            );
        });
    }
}
