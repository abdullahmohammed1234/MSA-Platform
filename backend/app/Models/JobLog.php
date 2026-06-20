<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JobLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'job_uuid',
        'job_name',
        'queue',
        'status',
        'started_at',
        'completed_at',
        'duration',
        'failure_reason',
        'attempts',
        'payload',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'duration' => 'float',
        'payload' => 'array',
    ];
}
