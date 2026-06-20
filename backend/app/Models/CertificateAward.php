<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class CertificateAward extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'user_id',
        'certificate_id',
        'title',
        'description',
        'code',
        'verification_token',
        'pdf_path',
        'issued_by',
        'issued_at',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($award) {
            if (empty($award->uuid)) {
                $award->uuid = (string) Str::uuid();
            }
            if (empty($award->code)) {
                $award->code = 'CERT-' . strtoupper(Str::random(6)) . '-' . strtoupper(Str::random(6));
            }
            if (empty($award->verification_token)) {
                $award->verification_token = Str::random(32);
            }
        });
    }

    /**
     * Get the user who earned this award.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the certificate definition for this award.
     */
    public function certificate(): BelongsTo
    {
        return $this->belongsTo(Certificate::class);
    }

    /**
     * Get the administrator who manually issued this award, if applicable.
     */
    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /**
     * Get the verification log entries.
     */
    public function verifications(): HasMany
    {
        return $this->hasMany(CertificateVerification::class);
    }
}
