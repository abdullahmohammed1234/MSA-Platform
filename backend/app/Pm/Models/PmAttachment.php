<?php

namespace App\Pm\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class PmAttachment extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'pm_attachments';

    protected $fillable = [
        'uuid',
        'project_id',
        'task_id',
        'user_id',
        'original_name',
        'storage_name',
        'file_path',
        'disk',
        'mime_type',
        'file_size',
    ];

    protected $casts = [
        'file_size' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($attachment) {
            if (empty($attachment->uuid)) {
                $attachment->uuid = (string) Str::uuid();
            }
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(PmProject::class, 'project_id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(PmTask::class, 'task_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

