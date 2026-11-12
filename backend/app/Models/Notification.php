<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'user_id',
        'type',
        'title',
        'message',
        'data',
        'read_at',
    ];

    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
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

    /**
     * Get the user who owns this notification.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope a query to only include notifications accessible to the user under Option B.
     * Excludes PMS notifications tied to projects the user is no longer authorized to access.
     */
    public function scopeAccessibleToUser($query, User $user)
    {
        $accessibleProjectIds = \App\Pm\Models\PmProject::accessibleTo($user)->pluck('id')->all();

        $allowedIds = [];
        foreach ($accessibleProjectIds as $id) {
            $allowedIds[] = (int) $id;
            $allowedIds[] = (string) $id;
        }

        return $query->where(function ($q) use ($allowedIds) {
            // 1. Non-PMS notifications
            $q->where(function ($nonPm) {
                $nonPm->whereNull('data->project_id')
                    ->where('type', 'not like', '%Pm%')
                    ->where(function ($d) {
                        $d->whereNull('data->type')
                          ->orWhere('data->type', 'not like', 'pm.%');
                    });
            });

            // 2. PMS notifications with an accessible project_id
            if (!empty($allowedIds)) {
                $q->orWhere(function ($pm) use ($allowedIds) {
                    $pm->whereIn('data->project_id', $allowedIds);
                });

                // 3. General PMS notifications (e.g. daily digest) if user has active projects
                $q->orWhere(function ($generalPm) {
                    $generalPm->whereNull('data->project_id')
                        ->where(function ($t) {
                            $t->where('type', 'like', '%Pm%')
                              ->orWhere('data->type', 'like', 'pm.%');
                        });
                });
            }
        });
    }

    /**
     * Check if this notification is currently accessible to the user under Option B.
     */
    public function isAccessibleToUser(User $user): bool
    {
        if ((int) $this->user_id !== (int) $user->id) {
            return false;
        }

        $data = $this->data ?? [];
        $isPmType = str_contains($this->type, 'Pm') || (isset($data['type']) && str_starts_with($data['type'], 'pm.'));
        $projectId = $data['project_id'] ?? null;

        if (!$isPmType && !$projectId) {
            return true;
        }

        if ($projectId) {
            $project = \App\Pm\Models\PmProject::find($projectId);
            if (!$project) {
                return false;
            }
            return app(\App\Pm\Services\ProjectAccessService::class)->canViewProject($user, $project);
        }

        return \App\Pm\Models\PmProject::accessibleTo($user)->exists();
    }

    /**
     * Scope a query to only include unread notifications.
     */
    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    /**
     * Scope a query to only include read notifications.
     */
    public function scopeRead($query)
    {
        return $query->whereNotNull('read_at');
    }

    /**
     * Mark the notification as read.
     */
    public function markAsRead(): void
    {
        $this->update(['read_at' => now()]);
    }

    /**
     * Mark the notification as unread.
     */
    public function markAsUnread(): void
    {
        $this->update(['read_at' => null]);
    }
}
