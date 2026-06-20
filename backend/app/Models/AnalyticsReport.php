<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AnalyticsReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'title',
        'type',
        'filters',
        'generated_by',
        'generated_at',
        'file_path',
    ];

    protected $casts = [
        'filters' => 'array',
        'generated_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function generatedBy()
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
