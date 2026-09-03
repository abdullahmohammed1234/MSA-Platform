<?php

namespace App\Donations\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class DonationRefund extends Model
{
    use HasFactory;

    protected $table = 'donation_refunds';

    protected $fillable = [
        'uuid',
        'donation_id',
        'amount_cents',
        'currency',
        'reason',
        'square_refund_id',
        'status',
        'processed_by',
        'processed_at',
    ];

    protected $casts = [
        'amount_cents' => 'integer',
        'processed_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function donation(): BelongsTo
    {
        return $this->belongsTo(Donation::class);
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
