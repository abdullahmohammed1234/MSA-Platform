<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SecurityEvent extends Model
{
    use HasFactory;

    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'event_type',
        'severity',
        'ip_address',
        'user_agent',
        'url',
        'method',
        'payload',
        'details',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
