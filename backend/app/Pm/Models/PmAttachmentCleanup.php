<?php

namespace App\Pm\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PmAttachmentCleanup extends Model
{
    use HasFactory;

    protected $table = 'pm_attachment_cleanups';

    protected $fillable = [
        'project_id',
        'file_path',
        'disk',
        'attempts',
        'last_error',
        'status',
    ];

    protected $casts = [
        'attempts' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(PmProject::class, 'project_id');
    }
}

