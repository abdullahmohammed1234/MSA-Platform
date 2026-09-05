<?php

namespace App\Mlibms\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Book extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'mlibms_books';

    protected $fillable = [
        'uuid',
        'title',
        'slug',
        'subtitle',
        'primary_category_id',
        'publisher_id',
        'isbn_10',
        'isbn_13',
        'edition',
        'publication_year',
        'language',
        'summary',
        'cover_image_url',
        'default_loan_days',
        'is_reference_only',
    ];

    protected $casts = [
        'publication_year' => 'integer',
        'default_loan_days' => 'integer',
        'is_reference_only' => 'boolean',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Book $book) {
            if (empty($book->uuid)) {
                $book->uuid = (string) Str::uuid();
            }
            if (empty($book->slug)) {
                $baseSlug = Str::slug($book->title) ?: 'book';
                $slug = $baseSlug;
                $count = 1;
                while (static::where('slug', $slug)->exists()) {
                    $slug = "{$baseSlug}-{$count}";
                    $count++;
                }
                $book->slug = $slug;
            }
        });
    }

    public function primaryCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'primary_category_id');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(Publisher::class, 'publisher_id');
    }

    public function authors(): BelongsToMany
    {
        return $this->belongsToMany(Author::class, 'mlibms_book_authors', 'book_id', 'author_id')
            ->withPivot('role');
    }

    public function copies(): HasMany
    {
        return $this->hasMany(Copy::class, 'book_id');
    }

    public function availableCopies(): HasMany
    {
        return $this->copies()->where('status', 'available');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class, 'book_id');
    }

    public function pendingReservations(): HasMany
    {
        return $this->reservations()->whereIn('status', ['pending', 'ready_for_pickup'])->orderBy('queue_position');
    }
}
