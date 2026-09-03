<?php

namespace App\Spms\Models;

use App\Models\User;
use App\Spms\Enums\OrganizationStatus;
use App\Spms\Enums\RelationshipType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Organization extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'spms_organizations';

    protected $fillable = [
        'uuid',
        'legal_name',
        'display_name',
        'relationship_type',
        'status',
        'industry',
        'website_url',
        'phone',
        'email',
        'address_line1',
        'city',
        'province',
        'postal_code',
        'country',
        'account_owner_id',
        'notes',
        'logo_url',
        'is_publicly_featured',
    ];

    protected $casts = [
        'relationship_type' => RelationshipType::class,
        'status' => OrganizationStatus::class,
        'is_publicly_featured' => 'boolean',
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

    public function accountOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'account_owner_id');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class, 'organization_id');
    }

    public function sponsorships(): HasMany
    {
        return $this->hasMany(Sponsorship::class, 'organization_id');
    }

    public function communications(): HasMany
    {
        return $this->hasMany(Communication::class, 'organization_id');
    }

    public function followUps(): HasMany
    {
        return $this->hasMany(FollowUp::class, 'organization_id');
    }
}
