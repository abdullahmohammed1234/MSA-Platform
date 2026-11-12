<?php

namespace App\Pm\Enums;

enum DepartmentRole: string
{
    case Lead = 'lead';
    case Coordinator = 'coordinator';
    case Member = 'member';

    public function label(): string
    {
        return match ($this) {
            self::Lead => 'Department Lead',
            self::Coordinator => 'Coordinator',
            self::Member => 'Team Member',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
