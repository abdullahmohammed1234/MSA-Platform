<?php

namespace App\Spms\Models;

use App\Models\User;
use App\Spms\Enums\DeliverableType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Deliverable extends Model
{
    use HasFactory;

    protected $table = 'spms_deliverables';

    protected $fillable = [
        'uuid',
        'sponsorship_id',
        'title',
        'description',
        'deliverable_type',
        'due_date',
        'assigned_to',
        'status',
    ];

    protected $casts = [
        'deliverable_type' => DeliverableType::class,
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

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function fulfillments(): HasMany
    {
        return $this->hasMany(Fulfillment::class, 'deliverable_id');
    }
}
