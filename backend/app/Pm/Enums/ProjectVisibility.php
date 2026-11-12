<?php

namespace App\Pm\Enums;

/**
 * Exactly two visibility levels permitted across the PMS.
 * Executive-only visibility is strictly prohibited.
 */
enum ProjectVisibility: string
{
    case DepartmentPrivate = 'department_private';
    case SharedMsa = 'shared_msa';

    public function label(): string
    {
        return match ($this) {
            self::DepartmentPrivate => 'Department-private',
            self::SharedMsa => 'Shared MSA',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::DepartmentPrivate => 'Department members and explicitly invited collaborators.',
            self::SharedMsa => 'All authorized MSA team members with PMS access.',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return [
            self::DepartmentPrivate->value,
            self::SharedMsa->value,
        ];
    }
}
