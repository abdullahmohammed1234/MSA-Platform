<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApplicationAccess extends Model
{
    use HasFactory;

    protected $table = 'application_access';

    protected $fillable = [
        'user_id',
        'application',
        'status',
        'granted_by',
    ];

    /**
     * Scope query to active application access grants.
     */
    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->where('status', 'active')->orWhereNull('status');
        });
    }

    /**
     * Determine if this grant is currently suspended.
     */
    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    /**
     * Determine if this grant is currently active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active' || $this->status === null;
    }

    /**
     * Get the user who has this application access.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the administrator who granted this application access.
     */
    public function granter()
    {
        return $this->belongsTo(User::class, 'granted_by');
    }
}
