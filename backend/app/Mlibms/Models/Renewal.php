<?php

namespace App\Mlibms\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Renewal extends Model
{
    use HasFactory;

    protected $table = 'mlibms_renewals';

    public $timestamps = false;

    protected $fillable = [
        'loan_id',
        'renewed_by',
        'previous_due_at',
        'new_due_at',
        'created_at',
    ];

    protected $casts = [
        'previous_due_at' => 'datetime',
        'new_due_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class, 'loan_id');
    }

    public function renewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'renewed_by');
    }
}
