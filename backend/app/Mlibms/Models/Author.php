<?php

namespace App\Mlibms\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Author extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'mlibms_authors';

    protected $fillable = [
        'uuid',
        'name',
        'slug',
        'biography',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Author $author) {
            if (empty($author->uuid)) {
                $author->uuid = (string) Str::uuid();
            }
            if (empty($author->slug)) {
                $baseSlug = Str::slug($author->name) ?: 'author';
                $slug = $baseSlug;
                $count = 1;
                while (static::where('slug', $slug)->exists()) {
                    $slug = "{$baseSlug}-{$count}";
                    $count++;
                }
                $author->slug = $slug;
            }
        });
    }

    public function books(): BelongsToMany
    {
        return $this->belongsToMany(Book::class, 'mlibms_book_authors', 'author_id', 'book_id')
            ->withPivot('role');
    }
}
