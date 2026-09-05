<?php

namespace App\Platform\Models;

use App\Models\User;
use App\Platform\Enums\AlertSeverity;
use App\Platform\Enums\AlertStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PlatformAlert extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'alert_key',
        'application',
        'severity',
        'title',
        'message',
        'context',
        'status',
        'acknowledged_by',
        'acknowledged_at',
        'resolved_by',
        'resolved_at',
    ];

    protected $casts = [
        'severity' => AlertSeverity::class,
        'status' => AlertStatus::class,
        'context' => 'array',
        'acknowledged_at' => 'datetime',
        'resolved_at' => 'datetime',
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

    public function acknowledgedBy()
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    public function resolvedBy()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
