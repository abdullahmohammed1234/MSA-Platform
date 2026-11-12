<?php

namespace App\Pm\Enums;

enum DependencyType: string
{
    case FinishToStart = 'finish_to_start';
    case StartToStart = 'start_to_start';
    case FinishToFinish = 'finish_to_finish';

    public function label(): string
    {
        return match ($this) {
            self::FinishToStart => 'Finish-to-Start (FS)',
            self::StartToStart => 'Start-to-Start (SS)',
            self::FinishToFinish => 'Finish-to-Finish (FF)',
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

