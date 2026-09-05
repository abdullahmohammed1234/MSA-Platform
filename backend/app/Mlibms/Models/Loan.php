<?php

namespace App\Mlibms\Models;

use App\Models\User;
use App\Mlibms\Enums\LoanStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Loan extends Model
{
    use HasFactory;

    protected $table = 'mlibms_loans';

    protected $fillable = [
        'uuid',
        'copy_id',
        'member_id',
        'checked_out_by',
        'returned_to',
        'checked_out_at',
        'due_at',
        'returned_at',
        'renewed_count',
        'last_renewed_at',
        'reminder_sent_at',
        'status',
        'staff_notes',
    ];

    protected $casts = [
        'status' => LoanStatus::class,
        'checked_out_at' => 'datetime',
        'due_at' => 'datetime',
        'returned_at' => 'datetime',
        'last_renewed_at' => 'datetime',
        'reminder_sent_at' => 'datetime',
        'renewed_count' => 'integer',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Loan $loan) {
            if (empty($loan->uuid)) {
                $loan->uuid = (string) Str::uuid();
            }
        });
    }

    public function copy(): BelongsTo
    {
        return $this->belongsTo(Copy::class, 'copy_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    public function checkedOutBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_out_by');
    }

    public function returnedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_to');
    }

    public function renewals(): HasMany
    {
        return $this->hasMany(Renewal::class, 'loan_id');
    }

    public function isOverdue(): bool
    {
        return $this->status === LoanStatus::OVERDUE || ($this->returned_at === null && $this->due_at->isPast());
    }
}
