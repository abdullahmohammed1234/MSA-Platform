<?php

namespace App\Pm\Enums;

enum ProjectRole: string
{
    case Owner = 'owner';
    case Manager = 'manager';
    case Contributor = 'contributor';
    case Viewer = 'viewer';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Manager => 'Manager',
            self::Contributor => 'Contributor',
            self::Viewer => 'Viewer',
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
