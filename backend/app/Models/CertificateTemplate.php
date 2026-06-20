<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class CertificateTemplate extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'name',
        'title_template',
        'description_template',
        'layout',
        'branding',
        'signatures',
        'background_asset',
        'status',
    ];

    protected $casts = [
        'branding' => 'array',
        'signatures' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($template) {
            if (empty($template->uuid)) {
                $template->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Get the certificates that use this template.
     */
    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }
}
