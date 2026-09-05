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
                $latest = static::max('id') + 1;
                $copy->accession_number = 'MLIB-A-' . str_pad((string) $latest, 6, '0', STR_PAD_LEFT);
            }
            if (empty($copy->barcode)) {
                $latest = static::max('id') + 1;
                $copy->barcode = 'MLIB-C-' . str_pad((string) $latest, 6, '0', STR_PAD_LEFT);
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
