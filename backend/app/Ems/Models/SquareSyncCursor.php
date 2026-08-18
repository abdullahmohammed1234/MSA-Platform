<?php

namespace App\Ems\Models;

use Illuminate\Database\Eloquent\Model;

class SquareSyncCursor extends Model
{
    protected $table = 'ems_square_sync_cursors';

    protected $fillable = [
        'key',
        'cursor_value',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public static function getValue(string $key): ?string
    {
        $row = static::query()->where('key', $key)->first();

        return $row?->cursor_value;
    }

    public static function putValue(string $key, ?string $value, array $metadata = []): self
    {
        $row = static::query()->firstOrNew(['key' => $key]);
        $row->cursor_value = $value;
        $row->metadata = array_merge($row->metadata ?? [], $metadata);
        $row->save();

        return $row;
    }
}
