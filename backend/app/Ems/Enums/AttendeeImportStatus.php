<?php

namespace App\Ems\Enums;

enum AttendeeImportStatus: string
{
    case Pending = 'pending';
    case Validating = 'validating';
    case Previewed = 'previewed';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Validating => 'Validating',
            self::Previewed => 'Previewed',
            self::Processing => 'Processing',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
