<?php

namespace App\Spms\Models;

use App\Ems\Models\Event as EmsEvent;
use App\Models\User;
use App\Spms\Enums\OpportunityType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Opportunity extends Model
{
    use HasFactory;

    protected $table = 'spms_opportunities';

    protected $fillable = [
        'uuid',
        'title',
        'slug',
        'description',
        'opportunity_type',
        'event_id',
        'target_amount_cents',
        'start_date',
        'end_date',
        'is_public',
        'status',
        'created_by',
    ];

    protected $casts = [
        'opportunity_type' => OpportunityType::class,
        'target_amount_cents' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
        'is_public' => 'boolean',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Model $model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
            if (empty($model->slug)) {
                $model->slug = Str::slug($model->title).'-'.Str::random(5);
            }
        });
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(EmsEvent::class, 'event_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function packages(): HasMany
    {
        return $this->hasMany(Package::class, 'opportunity_id');
    }

    public function sponsorships(): HasMany
    {
        return $this->hasMany(Sponsorship::class, 'opportunity_id');
    }
}
