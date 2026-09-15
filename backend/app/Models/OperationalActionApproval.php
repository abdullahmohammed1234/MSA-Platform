<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class OperationalActionApproval extends Model
{
    use HasFactory;

    protected $table = 'operational_action_approvals';

    protected $fillable = [
        'uuid',
        'operational_alert_id',
        'action_key',
        'status',
        'requested_by',
        'approved_by',
        'rejected_by',
        'request_reason',
        'decision_reason',
        'requested_at',
        'decided_at',
        'expires_at',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'decided_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    protected $appends = [
        'alert_id',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
            if (empty($model->requested_at)) {
                $model->requested_at = now();
            }
            if (empty($model->expires_at)) {
                $model->expires_at = now()->addHours(24);
            }
        });
    }

    public function getAlertIdAttribute()
    {
        return $this->attributes['operational_alert_id'] ?? null;
    }

    public function setAlertIdAttribute($value)
    {
        $this->attributes['operational_alert_id'] = $value;
    }

    public function alert(): BelongsTo
    {
        return $this->belongsTo(OperationalAlert::class, 'operational_alert_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }
}
