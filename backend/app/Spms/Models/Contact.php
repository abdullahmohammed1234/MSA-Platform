<?php

namespace App\Spms\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Contact extends Model
{
    use HasFactory;

    protected $table = 'spms_contacts';

    protected $fillable = [
        'uuid',
        'organization_id',
        'first_name',
        'last_name',
        'title',
        'email',
        'phone',
        'is_primary',
        'preferred_contact_method',
        'notes',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Model $model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }
}
