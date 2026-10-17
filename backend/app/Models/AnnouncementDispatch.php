<?php

namespace App\Models;

use App\Models\CMS\Announcement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One logical announcement send: the first publication, an intentional resend,
 * or an admin broadcast. Recipients are tracked in announcement_deliveries.
 */
class AnnouncementDispatch extends Model
{
    public const KIND_INITIAL = 'initial';
    public const KIND_RESEND = 'resend';
    public const KIND_BROADCAST = 'broadcast';

    public const STATUS_PREPARING = 'preparing';
    public const STATUS_SENDING = 'sending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_COMPLETED_WITH_FAILURES = 'completed_with_failures';

    public const ACTIVE_STATUSES = [self::STATUS_PREPARING, self::STATUS_SENDING];

    protected $fillable = [
        'uuid',
        'dedupe_key',
        'announcement_id',
        'kind',
        'audience',
        'title',
        'excerpt',
        'slug',
        'status',
        'recipients_total',
        'created_by',
        'prepared_at',
        'completed_at',
    ];

    protected $casts = [
        'recipients_total' => 'integer',
        'prepared_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(AnnouncementDelivery::class, 'dispatch_id');
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE_STATUSES, true);
    }
}
