<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class PlatformChange extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'platform_changes';

    protected $fillable = [
        'uuid',
        'release_id',
        'change_identifier',
        'category',
        'title',
        'description',
        'reason',
        'author_id',
        'environment',
        'affected_applications',
        'affected_services',
        'impact_level',
        'validation_status',
        'audit_log_id',
    ];

    protected $casts = [
        'affected_applications' => 'array',
        'affected_services' => 'array',
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

    public function release(): BelongsTo
    {
        return $this->belongsTo(PlatformRelease::class, 'release_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function auditLog(): BelongsTo
    {
        return $this->belongsTo(AuditLog::class, 'audit_log_id');
    }
}
