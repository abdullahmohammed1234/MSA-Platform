<?php

namespace App\Mlibms\Enums;

enum LoanStatus: string
{
    CASE ACTIVE = 'active';
    CASE RETURNED = 'returned';
    CASE OVERDUE = 'overdue';
    CASE LOST = 'lost';
    CASE DAMAGED = 'damaged';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Active',
            self::RETURNED => 'Returned',
            self::OVERDUE => 'Overdue',
            self::LOST => 'Lost',
            self::DAMAGED => 'Damaged',
        };
    }
}
