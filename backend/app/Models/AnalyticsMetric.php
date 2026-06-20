<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AnalyticsMetric extends Model
{
    use HasFactory;

    protected $fillable = [
        'metric_key',
        'metric_value',
        'period',
        'recorded_at',
    ];

    protected $casts = [
        'metric_value' => 'double',
        'recorded_at' => 'datetime',
    ];
}
