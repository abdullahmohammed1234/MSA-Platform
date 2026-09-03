<?php

namespace App\Store\Support;

final class StoreRoles
{
    public const STORE_ADMINISTRATOR = 'store-administrator';

    public static function defaultGrants(): array
    {
        return [
            self::STORE_ADMINISTRATOR => array_keys(StorePermissions::definitions()),
        ];
    }
}
