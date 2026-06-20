<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CertificateVerification extends Model
{
    use HasFactory;

    protected $fillable = [
        'certificate_award_id',
        'ip_address',
        'user_agent',
        'verified_at',
    ];

    protected $casts = [
        'verified_at' => 'datetime',
    ];

    /**
     * Get the certificate award that was verified.
     */
    public function award(): BelongsTo
    {
        return $this->belongsTo(CertificateAward::class, 'certificate_award_id');
    }
}
