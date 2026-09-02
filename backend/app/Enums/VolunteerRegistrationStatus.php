<?php

namespace App\Enums;

enum VolunteerRegistrationStatus: string
{
    case New = 'new';
    case Contacted = 'contacted';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Contacted => 'Contacted',
            self::Accepted => 'Accepted',
            self::Rejected => 'Rejected',
            self::Archived => 'Archived',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
