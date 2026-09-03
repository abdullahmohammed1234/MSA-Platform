<?php

namespace App\Spms\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Agreement extends Model
{
    use HasFactory;

    protected $table = 'spms_agreements';

    protected $fillable = [
        'uuid',
        'sponsorship_id',
        'agreement_number',
        'status',
        'signed_at',
        'terms_and_conditions',
        'document_url',
        'executed_by_name',
        'executed_by_title',
    ];

    protected $casts = [
        'signed_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Model $model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
            if (empty($model->agreement_number)) {
                $model->agreement_number = 'AGR-'.date('Ymd').'-'.strtoupper(Str::random(6));
            }
        });
    }

    public function sponsorship(): BelongsTo
    {
        return $this->belongsTo(Sponsorship::class, 'sponsorship_id');
    }
}
