<?php

namespace App\Spms\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Communication extends Model
{
    use HasFactory;

    protected $table = 'spms_communications';

    protected $fillable = [
        'organization_id',
        'sponsorship_id',
        'contact_id',
        'logged_by',
        'interaction_type',
        'subject',
        'body',
        'interaction_at',
    ];

    protected $casts = [
        'interaction_at' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function sponsorship(): BelongsTo
    {
        return $this->belongsTo(Sponsorship::class, 'sponsorship_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function logger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'logged_by');
    }
}
