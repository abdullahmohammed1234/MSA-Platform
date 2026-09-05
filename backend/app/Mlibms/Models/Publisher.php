<?php

namespace App\Mlibms\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Publisher extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'mlibms_publishers';

    protected $fillable = [
        'uuid',
        'name',
        'slug',
        'website',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Publisher $publisher) {
            if (empty($publisher->uuid)) {
                $publisher->uuid = (string) Str::uuid();
            }
            if (empty($publisher->slug)) {
                $baseSlug = Str::slug($publisher->name) ?: 'publisher';
                $slug = $baseSlug;
                $count = 1;
                while (static::where('slug', $slug)->exists()) {
                    $slug = "{$baseSlug}-{$count}";
                    $count++;
                }
                $publisher->slug = $slug;
            }
        });
    }

    public function books(): HasMany
    {
        return $this->hasMany(Book::class, 'publisher_id');
    }
}
