<?php

namespace App\Ems\Enums;

enum SquareCatalogSyncStatus: string
{
    case Pending = 'pending';
    case Synced = 'synced';
    case Failed = 'failed';
    case Archived = 'archived';
    case Conflict = 'conflict';
    case NotSynced = 'not_synced';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Synced => 'Connected',
            self::Failed => 'Failed',
            self::Archived => 'Archived',
            self::Conflict => 'Conflict',
            self::NotSynced => 'Not synced',
        };
    }
}
