<?php

namespace App\Ems\Models;

use App\Ems\Enums\SquareCatalogSyncStatus;
use App\Ems\Models\Concerns\HasEmsUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SquareCatalogMapping extends Model
{
    use HasEmsUuid;

    protected $table = 'ems_square_catalog_mappings';

    protected $fillable = [
        'uuid',
        'event_id',
        'ticket_type_id',
        'square_catalog_item_id',
        'square_catalog_variation_id',
        'square_location_id',
        'catalog_item_version',
        'catalog_variation_version',
        'sync_status',
        'ems_managed',
        'last_synced_at',
        'last_error',
        'last_conflict_at',
        'last_conflict_summary',
        'retry_count',
    ];

    protected function casts(): array
    {
        return [
            'sync_status' => SquareCatalogSyncStatus::class,
            'ems_managed' => 'boolean',
            'last_synced_at' => 'datetime',
            'last_conflict_at' => 'datetime',
            'catalog_item_version' => 'integer',
            'catalog_variation_version' => 'integer',
            'retry_count' => 'integer',
        ];
    }

    protected $attributes = [
        'sync_status' => SquareCatalogSyncStatus::Pending->value,
        'ems_managed' => true,
        'retry_count' => 0,
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class, 'ticket_type_id');
    }
}
