<?php

namespace App\Mlibms\Models;

use App\Mlibms\Enums\CopyCondition;
use App\Mlibms\Enums\CopyStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Copy extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'mlibms_copies';

    protected $fillable = [
        'uuid',
        'book_id',
        'location_id',
        'barcode',
        'accession_number',
        'condition',
        'status',
        'acquisition_date',
        'acquisition_cost_cents',
        'replacement_cost_cents',
        'notes',
    ];

    protected $casts = [
        'condition' => CopyCondition::class,
        'status' => CopyStatus::class,
        'acquisition_date' => 'date',
        'acquisition_cost_cents' => 'integer',
        'replacement_cost_cents' => 'integer',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Copy $copy) {
            if (empty($copy->uuid)) {
                $copy->uuid = (string) Str::uuid();
            }
            if (empty($copy->accession_number)) {
                $nextId = (static::withTrashed()->max('id') ?? 0) + 1;
                do {
                    $acc = 'MLIB-A-' . str_pad((string) $nextId, 6, '0', STR_PAD_LEFT);
                    $nextId++;
                } while (static::withTrashed()->where('accession_number', $acc)->exists());
                $copy->accession_number = $acc;
            }
            if (empty($copy->barcode)) {
                $nextId = (static::withTrashed()->max('id') ?? 0) + 1;
                do {
                    $bar = 'MLIB-C-' . str_pad((string) $nextId, 6, '0', STR_PAD_LEFT);
                    $nextId++;
                } while (static::withTrashed()->where('barcode', $bar)->exists());
                $copy->barcode = $bar;
            }
        });
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class, 'book_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class, 'copy_id');
    }

    public function activeLoan(): ?Loan
    {
        return $this->loans()->where('status', 'active')->first();
    }
}
