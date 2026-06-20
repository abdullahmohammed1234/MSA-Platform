<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use HasFactory;

    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'action',
        'target_type',
        'target_id',
        'description',
        'payload',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (request()) {
                $model->ip_address = $model->ip_address ?? request()->ip();
                $model->user_agent = $model->user_agent ?? request()->header('User-Agent');
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function target()
    {
        return $this->morphTo();
    }
}
