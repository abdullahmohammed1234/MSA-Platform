<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Certificate extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'certificate_template_id',
        'title',
        'description',
        'type', // course, learning_path, manual
        'course_id',
        'learning_path_id',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($certificate) {
            if (empty($certificate->uuid)) {
                $certificate->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Get the template that defines this certificate's style.
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(CertificateTemplate::class, 'certificate_template_id');
    }

    /**
     * Get the course associated with the certificate, if any.
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Get the learning path associated with the certificate, if any.
     */
    public function learningPath(): BelongsTo
    {
        return $this->belongsTo(LearningPath::class);
    }

    /**
     * Get the requirements for this certificate.
     */
    public function requirements(): HasMany
    {
        return $this->hasMany(CertificateRequirement::class);
    }

    /**
     * Get the awards issued for this certificate.
     */
    public function awards(): HasMany
    {
        return $this->hasMany(CertificateAward::class);
    }
}
