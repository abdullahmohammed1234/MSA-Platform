<?php

namespace App\Pm\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PmActivityLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'pm_activity_logs';

    protected $fillable = [
        'project_id',
        'user_id',
        'action',
        'entity_type',
        'entity_id',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(PmProject::class, 'project_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public static function record(
        ?int $actorId,
        string $action,
        string $subjectType,
        ?int $subjectId = null,
        ?int $projectId = null,
        array $metadata = []
    ): self {
        return static::create([
            'project_id' => $projectId,
            'user_id' => $actorId,
            'action' => $action,
            'entity_type' => $subjectType,
            'entity_id' => $subjectId,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }
}
