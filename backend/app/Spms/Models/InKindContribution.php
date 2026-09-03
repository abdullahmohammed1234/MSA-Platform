<?php

namespace App\Spms\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class InKindContribution extends Model
{
    use HasFactory;

    protected $table = 'spms_in_kind_contributions';

    protected $fillable = [
        'uuid',
        'sponsorship_id',
        'category',
        'description',
        'estimated_value_cents',
        'agreed_value_cents',
        'quantity',
        'received_at',
        'status',
        'received_by',
        'notes',
    ];

    protected $casts = [
        'estimated_value_cents' => 'integer',
        'agreed_value_cents' => 'integer',
        'quantity' => 'integer',
        'received_at' => 'datetime',
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

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
