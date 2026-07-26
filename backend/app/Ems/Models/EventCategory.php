<?php

namespace App\Ems\Models;

use App\Ems\Models\Concerns\HasEmsUuid;
use Database\Factories\Ems\EventCategoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string|null $color
 * @property bool $is_active
 * @property int $sort_order
 */
class EventCategory extends Model
{
    /** @use HasFactory<EventCategoryFactory> */
    use HasEmsUuid, HasFactory, SoftDeletes;

    protected $table = 'ems_event_categories';

    protected $fillable = [
        'uuid',
        'name',
        'slug',
        'description',
        'color',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Mirrors the column defaults so a freshly created model reports its real
     * state without an extra round trip to the database.
     */
    protected $attributes = [
        'is_active' => true,
        'sort_order' => 0,
    ];

    protected static function newFactory(): EventCategoryFactory
    {
        return EventCategoryFactory::new();
    }

    /**
     * @return HasMany<Event, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class, 'category_id');
    }

    /**
     * @param  Builder<EventCategory>  $query
     * @return Builder<EventCategory>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * The order categories are presented in: explicit sort order, then name.
     *
     * @param  Builder<EventCategory>  $query
     * @return Builder<EventCategory>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
