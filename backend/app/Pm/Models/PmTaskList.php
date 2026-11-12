<?php

namespace App\Pm\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class PmTaskList extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'pm_task_lists';

    protected $fillable = [
        'uuid',
        'project_id',
        'name',
        'order_index',
        'color',
        'created_by',
    ];

    protected $casts = [
        'order_index' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($list) {
            if (empty($list->uuid)) {
                $list->uuid = (string) Str::uuid();
            }
            if (empty($list->created_by)) {
                $list->created_by = auth()->id() ?? ($list->project ? $list->project->owner_id : 1);
            }
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(PmProject::class, 'project_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(PmTask::class, 'task_list_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

