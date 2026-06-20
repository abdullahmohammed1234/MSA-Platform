<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AnalyticsEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'user_id',
        'session_id',
        'module',
        'event_type',
        'event_name',
        'entity_type',
        'entity_id',
        'metadata',
        'occurred_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'occurred_at' => 'datetime',
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

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function session()
    {
        return $this->belongsTo(AnalyticsSession::class, 'session_id');
    }

    public function entity()
    {
        return $this->morphTo();
    }
}
