<?php

namespace App\Donations\Models;

use App\Donations\Enums\DonationStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Donation extends Model
{
    use HasFactory;

    protected $table = 'donations';

    protected $fillable = [
        'uuid',
        'donation_number',
        'user_id',
        'donor_name',
        'donor_email',
        'amount_cents',
        'currency',
        'status',
        'is_anonymous',
        'dedication',
        'square_checkout_id',
        'square_order_id',
        'square_payment_id',
        'paid_at',
        'refunded_at',
    ];

    protected $casts = [
        'amount_cents' => 'integer',
        'is_anonymous' => 'boolean',
        'status' => DonationStatus::class,
        'paid_at' => 'datetime',
        'refunded_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
            if (empty($model->donation_number)) {
                $model->donation_number = 'DON-'.date('Ymd').'-'.strtoupper(Str::random(6));
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(DonationRefund::class);
    }

    public function scopePaid($query)
    {
        return $query->where('status', DonationStatus::Paid->value);
    }

    public function scopePending($query)
    {
        return $query->where('status', DonationStatus::Pending->value);
    }

    public function getFormattedAmountAttribute(): string
    {
        return '$'.number_format($this->amount_cents / 100, 2).' '.$this->currency;
    }
}
