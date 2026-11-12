<?php

namespace App\Pm\Models;

use App\Models\User;
use App\Pm\Enums\InvitationStatus;
use App\Pm\Enums\ProjectRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PmProjectMember extends Model
{
    use HasFactory;

    protected $table = 'pm_project_members';

    protected $fillable = [
        'project_id',
        'user_id',
        'project_role',
        'invitation_status',
        'invited_by',
        'invited_at',
        'accepted_at',
        'revoked_at',
    ];

    protected $casts = [
        'project_role' => ProjectRole::class,
        'invitation_status' => InvitationStatus::class,
        'invited_at' => 'datetime',
        'accepted_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(PmProject::class, 'project_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function isAccepted(): bool
    {
        return $this->invitation_status === InvitationStatus::Accepted;
    }

    public function isPending(): bool
    {
        return $this->invitation_status === InvitationStatus::Pending;
    }

    public function isOwner(): bool
    {
        return $this->project_role === ProjectRole::Owner;
    }

    public function isManager(): bool
    {
        return in_array($this->project_role, [ProjectRole::Owner, ProjectRole::Manager], true);
    }

    public function scopeAccepted($query)
    {
        return $query->where('invitation_status', InvitationStatus::Accepted->value);
    }

    public function scopePending($query)
    {
        return $query->where('invitation_status', InvitationStatus::Pending->value);
    }

    public function getRoleAttribute(): ?ProjectRole
    {
        return $this->project_role;
    }
}
