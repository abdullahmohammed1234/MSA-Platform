<?php

namespace App\Spms\Models;

use App\Models\User;
use App\Spms\Enums\FinancialStatus;
use App\Spms\Enums\FulfillmentStatus;
use App\Spms\Enums\SponsorshipStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Sponsorship extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'spms_sponsorships';

    protected $fillable = [
        'uuid',
        'sponsorship_number',
        'organization_id',
        'contact_id',
        'opportunity_id',
        'package_id',
        'title',
        'sponsorship_type',
        'status',
        'financial_status',
        'fulfillment_status',
        'total_committed_cents',
        'total_paid_cents',
        'in_kind_estimated_cents',
        'start_date',
        'end_date',
        'relationship_manager_id',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'status' => SponsorshipStatus::class,
        'financial_status' => FinancialStatus::class,
        'fulfillment_status' => FulfillmentStatus::class,
        'total_committed_cents' => 'integer',
        'total_paid_cents' => 'integer',
        'in_kind_estimated_cents' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Model $model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
            if (empty($model->sponsorship_number)) {
                $model->sponsorship_number = 'SPO-'.date('Ymd').'-'.strtoupper(Str::random(6));
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class, 'opportunity_id');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class, 'package_id');
    }

    public function relationshipManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'relationship_manager_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function agreement(): HasOne
    {
        return $this->hasOne(Agreement::class, 'sponsorship_id');
    }

    public function commitments(): HasMany
    {
        return $this->hasMany(Commitment::class, 'sponsorship_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'sponsorship_id');
    }

    public function inKindContributions(): HasMany
    {
        return $this->hasMany(InKindContribution::class, 'sponsorship_id');
    }

    public function deliverables(): HasMany
    {
        return $this->hasMany(Deliverable::class, 'sponsorship_id');
    }

    public function communications(): HasMany
    {
        return $this->hasMany(Communication::class, 'sponsorship_id');
    }

    public function followUps(): HasMany
    {
        return $this->hasMany(FollowUp::class, 'sponsorship_id');
    }

    public function renewals(): HasMany
    {
        return $this->hasMany(Renewal::class, 'previous_sponsorship_id');
    }

    public function getOutstandingCentsAttribute(): int
    {
        return max(0, $this->total_committed_cents - $this->total_paid_cents);
    }
}
