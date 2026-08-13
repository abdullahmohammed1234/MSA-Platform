<?php

namespace App\Models\CMS;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class MediaCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'name',
        'slug',
    ];

    protected static function booted(): void
    {
        static::creating(function (MediaCategory $category) {
            if (empty($category->uuid)) {
                $category->uuid = (string) Str::uuid();
            }

            if (empty($category->slug) && !empty($category->name)) {
                $category->slug = static::uniqueSlugFromName($category->name);
            }
        });
    }

    public function media(): HasMany
    {
        return $this->hasMany(Media::class, 'category_id');
    }

    public static function uniqueSlugFromName(string $name): string
    {
        $base = Str::slug($name);
        if ($base === '') {
            $base = 'category';
        }

        $slug = $base;
        $suffix = 1;

        while (static::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
