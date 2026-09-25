<?php

namespace App\Models\CMS;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CmsPrayer extends Model
{
    use HasFactory;

    protected $table = 'cms_prayers';

    protected $fillable = [
        'uuid',
        'type',
        'title',
        'campus',
        'day',
        'is_enabled',
        'fajr_time',
        'dhuhr_time',
        'asr_time',
        'maghrib_time',
        'isha_time',
        'khutbah_time',
        'prayer_time',
        'location',
        'address',
        'notes',
        'timings_json',
        'display_order',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'timings_json' => 'array',
        'display_order' => 'integer',
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
