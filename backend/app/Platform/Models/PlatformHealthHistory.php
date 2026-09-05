<?php

namespace App\Platform\Models;

use App\Platform\Enums\SystemHealthStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlatformHealthHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'system_status',
        'operational_count',
        'degraded_count',
        'unavailable_count',
        'details',
        'recorded_at',
    ];

    protected $casts = [
        'system_status' => SystemHealthStatus::class,
        'details' => 'array',
        'recorded_at' => 'datetime',
    ];
}
