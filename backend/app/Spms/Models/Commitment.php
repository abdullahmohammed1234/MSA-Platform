<?php

namespace App\Spms\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Commitment extends Model
{
    use HasFactory;

    protected $table = 'spms_commitments';

    protected $fillable = [
        'uuid',
        'sponsorship_id',
        'commitment_type',
        'amount_cents',
        'due_date',
        'status',
        'notes',
    ];

    protected $casts = [
        'amount_cents' => 'integer',
        'due_date' => 'date',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Model $model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function sponsorship(): BelongsTo
    {
        return $this->belongsTo(Sponsorship::class, 'sponsorship_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'commitment_id');
    }
}
