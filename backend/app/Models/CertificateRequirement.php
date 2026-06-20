<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class CertificateRequirement extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'certificate_id',
        'type', // lesson_completion, quiz_completion, passing_score, course_completion, average_score, custom
        'parameters',
    ];

    protected $casts = [
        'parameters' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($requirement) {
            if (empty($requirement->uuid)) {
                $requirement->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Get the certificate this requirement belongs to.
     */
    public function certificate(): BelongsTo
    {
        return $this->belongsTo(Certificate::class);
    }
}
