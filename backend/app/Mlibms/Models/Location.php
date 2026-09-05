<?php

namespace App\Mlibms\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Location extends Model
{
    use HasFactory;

    protected $table = 'mlibms_locations';

    protected $fillable = [
        'uuid',
        'name',
        'code',
        'shelf_identifier',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Location $location) {
            if (empty($location->uuid)) {
                $location->uuid = (string) Str::uuid();
            }
        });
    }

    public function copies(): HasMany
    {
        return $this->hasMany(Copy::class, 'location_id');
    }
}
