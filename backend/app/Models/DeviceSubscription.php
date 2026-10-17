<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class DeviceSubscription extends Model
{
    use HasFactory;

    protected $table = 'device_subscriptions';

    protected $fillable = [
        'uuid',
        'user_id',
        'endpoint',
        'endpoint_hash',
        'public_key',
        'auth_token',
        'content_encoding',
        'device_name',
        'device_type',
        'user_agent',
        'is_active',
        'last_used_at',
        'revoked_at',
    ];

    /**
     * Always hide cryptographic keys and endpoints from standard serialization.
     */
    protected $hidden = [
        'id',
        'endpoint',
        'endpoint_hash',
        'public_key',
        'auth_token',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (DeviceSubscription $subscription) {
            if (empty($subscription->uuid)) {
                $subscription->uuid = (string) Str::uuid();
            }
            if (empty($subscription->endpoint_hash) && !empty($subscription->endpoint)) {
                $subscription->endpoint_hash = hash('sha256', $subscription->endpoint);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Return public-safe metadata for client presentation.
     */
    public function toSanitizedArray(): array
    {
        return [
            'id' => $this->uuid,
            'device_name' => $this->device_name ?: 'Web Browser',
            'device_type' => $this->device_type ?: 'browser',
            'is_active' => (bool) $this->is_active,
            'last_used_at' => $this->last_used_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }

    /**
     * Safely deactivate the subscription.
     */
    public function revoke(): void
    {
        $this->update([
            'is_active' => false,
            'revoked_at' => now(),
        ]);
    }
}
