<?php

namespace App\Spms\Models;

use App\Models\User;
use App\Spms\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Payment extends Model
{
    use HasFactory;

    protected $table = 'spms_payments';

    protected $fillable = [
        'uuid',
        'payment_number',
        'sponsorship_id',
        'commitment_id',
        'payment_method',
        'amount_cents',
        'currency',
        'status',
        'reference_number',
        'square_checkout_id',
        'square_order_id',
        'square_payment_id',
        'paid_at',
        'recorded_by',
        'notes',
    ];

    protected $casts = [
        'payment_method' => PaymentMethod::class,
        'amount_cents' => 'integer',
        'paid_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Model $model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
            if (empty($model->payment_number)) {
                $model->payment_number = 'PAY-'.date('Ymd').'-'.strtoupper(Str::random(6));
            }
        });
    }

    public function sponsorship(): BelongsTo
    {
        return $this->belongsTo(Sponsorship::class, 'sponsorship_id');
    }

    public function commitment(): BelongsTo
    {
        return $this->belongsTo(Commitment::class, 'commitment_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
