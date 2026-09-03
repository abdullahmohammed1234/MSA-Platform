<?php

namespace App\Store\Support;

final class StorePermissions
{
    public const MODULE = 'STORE';

    // Products
    public const PRODUCTS_VIEW = 'store.products.view';
    public const PRODUCTS_CREATE = 'store.products.create';
    public const PRODUCTS_UPDATE = 'store.products.update';
    public const PRODUCTS_DELETE = 'store.products.delete';

    // Inventory
    public const INVENTORY_VIEW = 'store.inventory.view';
    public const INVENTORY_UPDATE = 'store.inventory.update';

    // Orders
    public const ORDERS_VIEW = 'store.orders.view';
    public const ORDERS_UPDATE = 'store.orders.update';
    public const ORDERS_REFUND = 'store.orders.refund';

    /**
     * @return array<string, array{name: string, module: string, description: string}>
     */
    public static function definitions(): array
    {
        return [
            self::PRODUCTS_VIEW => [
                'name' => 'View Store Products',
                'module' => self::MODULE,
                'description' => 'View products in the Store admin portal.',
            ],
            self::PRODUCTS_CREATE => [
                'name' => 'Create Store Products',
                'module' => self::MODULE,
                'description' => 'Create new merchandise products and variants.',
            ],
            self::PRODUCTS_UPDATE => [
                'name' => 'Update Store Products',
                'module' => self::MODULE,
                'description' => 'Edit merchandise products, variants, and product images.',
            ],
            self::PRODUCTS_DELETE => [
                'name' => 'Delete Store Products',
                'module' => self::MODULE,
                'description' => 'Archive or delete merchandise products.',
            ],
            self::INVENTORY_VIEW => [
                'name' => 'View Inventory',
                'module' => self::MODULE,
                'description' => 'View stock levels and inventory adjustment logs.',
            ],
            self::INVENTORY_UPDATE => [
                'name' => 'Update Inventory',
                'module' => self::MODULE,
                'description' => 'Perform manual inventory stock adjustments.',
            ],
            self::ORDERS_VIEW => [
                'name' => 'View Store Orders',
                'module' => self::MODULE,
                'description' => 'View customer merchandise orders and details.',
            ],
            self::ORDERS_UPDATE => [
                'name' => 'Update Store Orders',
                'module' => self::MODULE,
                'description' => 'Update order fulfillment status.',
            ],
            self::ORDERS_REFUND => [
                'name' => 'Refund Store Orders',
                'module' => self::MODULE,
                'description' => 'Issue refunds for paid merchandise orders.',
            ],
        ];
    }
}
