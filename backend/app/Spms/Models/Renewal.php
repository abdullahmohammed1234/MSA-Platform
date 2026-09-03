<?php

namespace App\Spms\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Renewal extends Model
{
    use HasFactory;

    protected $table = 'spms_renewals';

    protected $fillable = [
        'previous_sponsorship_id',
        'new_sponsorship_id',
        'target_renewal_date',
        'proposed_amount_cents',
        'status',
        'owner_id',
        'notes',
    ];

    protected $casts = [
        'target_renewal_date' => 'date',
        'proposed_amount_cents' => 'integer',
    ];

    public function previousSponsorship(): BelongsTo
    {
        return $this->belongsTo(Sponsorship::class, 'previous_sponsorship_id');
    }

    public function newSponsorship(): BelongsTo
    {
        return $this->belongsTo(Sponsorship::class, 'new_sponsorship_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
