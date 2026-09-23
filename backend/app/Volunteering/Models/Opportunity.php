<?php

namespace App\Volunteering\Models;

use App\Ems\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Opportunity extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'volunteering_opportunities';

    protected $fillable = [
        'uuid',
        'title',
        'slug',
        'description',
        'event_id',
        'start_at',
        'end_at',
        'location',
        'capacity',
        'status',
        'published_at',
        'closed_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'published_at' => 'datetime',
        'closed_at' => 'datetime',
        'capacity' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
            if (empty($model->slug)) {
                $model->slug = Str::slug($model->title) . '-' . Str::random(5);
            }
        });
    }

    public function event(): BelongsTo
    {
        return $table_ref = $this->belongsTo(Event::class, 'event_id');
    }

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class, 'opportunity_id')->orderBy('ordering', 'asc');
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class, 'opportunity_id')->orderBy('start_at', 'asc');
    }

    public function signups(): HasMany
    {
        return $this->hasMany(Signup::class, 'opportunity_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'open');
    }
}
