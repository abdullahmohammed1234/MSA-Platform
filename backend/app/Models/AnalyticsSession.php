<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AnalyticsSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'user_id',
        'started_at',
        'ended_at',
        'duration',
        'device',
        'browser',
        'platform',
        'referrer',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
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

    public function events()
    {
        return $this->hasMany(AnalyticsEvent::class, 'session_id');
    }
}
