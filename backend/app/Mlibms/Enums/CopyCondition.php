<?php

namespace App\Mlibms\Enums;

enum CopyCondition: string
{
    CASE NEW = 'new';
    CASE GOOD = 'good';
    CASE FAIR = 'fair';
    CASE WORN = 'worn';
    CASE DAMAGED = 'damaged';

    public function label(): string
    {
        return match ($this) {
            self::NEW => 'New',
            self::GOOD => 'Good',
            self::FAIR => 'Fair',
            self::WORN => 'Worn',
            self::DAMAGED => 'Damaged',
        };
    }
}
