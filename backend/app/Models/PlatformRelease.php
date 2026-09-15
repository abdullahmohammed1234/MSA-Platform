<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class PlatformRelease extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'platform_releases';

    protected $fillable = [
        'uuid',
        'release_identifier',
        'application_version',
        'api_version',
        'frontend_build',
        'environment',
        'status',
        'is_active',
        'released_at',
        'release_notes',
        'created_by',
        'approved_by',
        'approved_at',
        'deployment_status',
        'migration_status',
        'post_release_verification_status',
        'rollback_readiness',
        'rollback_notes',
        'previous_release_id',
        'affected_applications',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'released_at' => 'datetime',
        'approved_at' => 'datetime',
        'affected_applications' => 'array',
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

    public function changes(): HasMany
    {
        return $this->hasMany(PlatformChange::class, 'release_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function previousRelease(): BelongsTo
    {
        return $this->belongsTo(PlatformRelease::class, 'previous_release_id');
    }
}
