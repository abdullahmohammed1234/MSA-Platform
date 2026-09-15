<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class OperationalAlert extends Model
{
    use HasFactory;

    protected $table = 'operational_alerts';

    protected $fillable = [
        'uuid',
        'fingerprint',
        'category',
        'severity',
        'status',
        'title',
        'description',
        'source_type',
        'source_id',
        'rule_key',
        'action_url',
        'metadata',
        'first_detected_at',
        'last_detected_at',
        'acknowledged_at',
        'acknowledged_by',
        'resolved_at',
        'resolved_by',
        'dismissed_at',
        'dismissed_by',
        'resolution_reason',
    ];

    protected $casts = [
        'metadata' => 'array',
        'first_detected_at' => 'datetime',
        'last_detected_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'resolved_at' => 'datetime',
        'dismissed_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
            if (empty($model->fingerprint)) {
                $model->fingerprint = static::computeFingerprint(
                    (string) $model->category,
                    (string) $model->source_type,
                    (string) $model->source_id,
                    (string) $model->rule_key
                );
            }
            if (empty($model->first_detected_at)) {
                $model->first_detected_at = now();
            }
            if (empty($model->last_detected_at)) {
                $model->last_detected_at = now();
            }
        });
    }

    public static function computeFingerprint(string $category, string $sourceType, string $sourceId, string $ruleKey): string
    {
        return md5(strtolower(trim($category)).'|'.strtolower(trim($sourceType)).'|'.trim($sourceId).'|'.strtolower(trim($ruleKey)));
    }

    public function acknowledgedBy()
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    public function resolvedBy()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function dismissedBy()
    {
        return $this->belongsTo(User::class, 'dismissed_by');
    }

    /**
     * Validate status transition matrix.
     */
    public function canTransitionTo(string $targetStatus): bool
    {
        $current = strtolower($this->status);
        $target = strtolower($targetStatus);

        if ($current === $target) {
            return false;
        }

        switch ($current) {
            case 'open':
                return in_array($target, ['acknowledged', 'resolved', 'dismissed'], true);
            case 'acknowledged':
                return in_array($target, ['resolved', 'dismissed', 'open'], true);
            case 'resolved':
            case 'dismissed':
                return $target === 'open';
            default:
                return false;
        }
    }
}
