<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class OperationalActionExecution extends Model
{
    use HasFactory;

    protected $table = 'operational_action_executions';

    protected $fillable = [
        'uuid',
        'operational_alert_id',
        'approval_id',
        'action_key',
        'status',
        'requested_by',
        'executed_by',
        'requested_at',
        'started_at',
        'completed_at',
        'failed_at',
        'before_snapshot',
        'after_snapshot',
        'result_summary',
        'error_code',
        'error_message',
    ];

    protected $casts = [
        'before_snapshot' => 'array',
        'after_snapshot' => 'array',
        'requested_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    protected $appends = [
        'alert_id',
        'summary',
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

    public function getSummaryAttribute()
    {
        return $this->attributes['result_summary'] ?? null;
    }

    public function setSummaryAttribute($value)
    {
        $this->attributes['result_summary'] = $value;
    }

    public function alert(): BelongsTo
    {
        return $this->belongsTo(OperationalAlert::class, 'operational_alert_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function executedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executed_by');
    }

    /**
     * Check if transition to target status is valid.
     */
    public function canTransitionTo(string $targetStatus): bool
    {
        $current = strtolower($this->status);
        $target = strtolower($targetStatus);

        if ($current === $target) {
            return false;
        }

        switch ($current) {
            case 'requested':
                return in_array($target, ['running', 'cancelled'], true);
            case 'running':
                return in_array($target, ['completed', 'failed'], true);
            default:
                return false;
        }
    }
}
