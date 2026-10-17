<?php

namespace App\Models;

use Database\Factories\SupportInquiryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class SupportInquiry extends Model
{
    /** @use HasFactory<SupportInquiryFactory> */
    use HasFactory, SoftDeletes;

    public const STATUS_OPEN = 'open';
    public const STATUS_IN_REVIEW = 'in_review';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_AWAITING_MEMBER = 'awaiting_member';
    public const STATUS_AWAITING_STAFF = 'awaiting_staff';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_CLOSED = 'closed';

    public const VALID_STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_IN_REVIEW,
        self::STATUS_IN_PROGRESS,
        self::STATUS_AWAITING_MEMBER,
        self::STATUS_AWAITING_STAFF,
        self::STATUS_RESOLVED,
        self::STATUS_CLOSED,
    ];

    public const CATEGORIES = [
        'general' => 'General MSA Support',
        'new_muslim' => 'New Muslim Support',
        'faith_questions' => 'Faith & Spiritual Questions',
        'campus_prayer' => 'Campus & Prayer Resources',
        'academic' => 'Academic & Exam Adjustments',
        'event_support' => 'Event & Registration Support',
        'feedback' => 'Feedback & Suggestions',
        'other' => 'Other Inquiries',
    ];

    protected $fillable = [
        'uuid',
        'reference_code',
        'user_id',
        'category',
        'subject',
        'status',
        'priority',
        'assigned_to_user_id',
        'last_activity_at',
        'closed_at',
    ];

    protected $casts = [
        'last_activity_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    protected $appends = [
        'assigned_staff',
    ];

    public function getAssignedStaffAttribute()
    {
        return $this->relationLoaded('assignedTo') ? $this->assignedTo : null;
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($inquiry) {
            if (empty($inquiry->uuid)) {
                $inquiry->uuid = (string) Str::uuid();
            }
            if (empty($inquiry->reference_code)) {
                $inquiry->reference_code = self::generateReferenceCode();
            }
            if (empty($inquiry->last_activity_at)) {
                $inquiry->last_activity_at = now();
            }
        });
    }

    public static function generateReferenceCode(): string
    {
        $prefix = 'INQ-' . date('Ym') . '-';
        do {
            $code = $prefix . strtoupper(Str::random(4));
        } while (self::withTrashed()->where('reference_code', $code)->exists());

        return $code;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportMessage::class, 'inquiry_id')->orderBy('created_at', 'asc');
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(SupportMessage::class, 'inquiry_id')->latestOfMany();
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    public function isResolved(): bool
    {
        return $this->status === self::STATUS_RESOLVED;
    }

    public static function findByIdentifierOrFail(string|int $id): self
    {
        $query = self::query();
        if (is_numeric($id)) {
            return $query->where('id', (int) $id)->firstOrFail();
        }

        return $query->where('reference_code', (string) $id)
            ->orWhere('uuid', (string) $id)
            ->firstOrFail();
    }
}
