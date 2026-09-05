<?php

namespace App\Mlibms\Models;

use App\Models\User;
use App\Mlibms\Enums\MembershipType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Member extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'mlibms_members';

    protected $fillable = [
        'uuid',
        'user_id',
        'library_card_number',
        'name',
        'email',
        'phone',
        'membership_type',
        'status',
        'max_active_loans',
        'notes',
        'registered_at',
        'suspended_at',
        'suspension_reason',
    ];

    protected $casts = [
        'membership_type' => MembershipType::class,
        'max_active_loans' => 'integer',
        'registered_at' => 'datetime',
        'suspended_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Member $member) {
            if (empty($member->uuid)) {
                $member->uuid = (string) Str::uuid();
            }
            if (empty($member->library_card_number)) {
                $latest = static::max('id') + 1;
                $member->library_card_number = 'LIB-CARD-' . str_pad((string) $latest, 6, '0', STR_PAD_LEFT);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class, 'member_id');
    }

    public function activeLoans(): HasMany
    {
        return $this->loans()->where('status', 'active');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class, 'member_id');
    }

    public function activeReservations(): HasMany
    {
        return $this->reservations()->whereIn('status', ['pending', 'ready_for_pickup']);
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    public function isGuest(): bool
    {
        return is_null($this->user_id);
    }
}
