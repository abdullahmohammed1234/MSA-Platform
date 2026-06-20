<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PerformanceMetric extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'url',
        'method',
        'duration_ms',
        'db_queries_count',
        'db_queries_time_ms',
        'memory_mb',
        'user_id',
        'created_at',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
