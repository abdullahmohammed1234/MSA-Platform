<?php

namespace App\Pm\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PmComment extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'pm_comments';

    protected $fillable = [
        'project_id',
        'task_id',
        'user_id',
        'body',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(PmProject::class, 'project_id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(PmTask::class, 'task_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
