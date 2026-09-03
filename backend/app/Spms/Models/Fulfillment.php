<?php

namespace App\Spms\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Fulfillment extends Model
{
    use HasFactory;

    protected $table = 'spms_fulfillments';

    protected $fillable = [
        'deliverable_id',
        'completed_at',
        'completed_by',
        'proof_url',
        'notes',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
    ];

    public function deliverable(): BelongsTo
    {
        return $this->belongsTo(Deliverable::class, 'deliverable_id');
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}
