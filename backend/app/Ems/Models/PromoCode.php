<?php

namespace App\Ems\Models;

use App\Ems\Models\Concerns\HasEmsUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class PromoCode extends Model
{
    use HasEmsUuid, HasFactory;

    protected $table = 'ems_promo_codes';

    protected $fillable = [
        'uuid',
        'code',
        'description',
        'discount_type',
        'discount_value',
        'usage_limit',
        'usage_per_attendee',
        'start_date',
        'end_date',
        'minimum_purchase',
        'is_active',
        'archived_at',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'usage_limit' => 'integer',
        'usage_per_attendee' => 'integer',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'minimum_purchase' => 'decimal:2',
        'is_active' => 'boolean',
        'archived_at' => 'datetime',
    ];

    public function events(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'ems_promo_code_event', 'promo_code_id', 'event_id');
    }

    public function ticketTypes(): BelongsToMany
    {
        return $this->belongsToMany(TicketType::class, 'ems_promo_code_ticket_type', 'promo_code_id', 'ticket_type_id');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class, 'promo_code_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'promo_code_id');
    }

    public function isValidFor(Event $event, ?TicketType $ticketType = null, ?User $user = null, float $orderAmount = 0.0, ?string $email = null): array
    {
        if (!$this->is_active || $this->archived_at !== null) {
            return ['valid' => false, 'reason' => 'This promo code is no longer active.'];
        }

        $now = now();
        if ($this->start_date && $now->lessThan($this->start_date)) {
            return ['valid' => false, 'reason' => 'This promo code is not active yet.'];
        }

        if ($this->end_date && $now->greaterThan($this->end_date)) {
            return ['valid' => false, 'reason' => 'This promo code has expired.'];
        }

        if ($this->minimum_purchase !== null && $orderAmount < (float) $this->minimum_purchase) {
            return ['valid' => false, 'reason' => sprintf('A minimum purchase of $%s is required to use this promo code.', number_format($this->minimum_purchase, 2))];
        }

        // Check events restriction
        if ($this->events()->exists() && !$this->events()->where('ems_events.id', $event->id)->exists()) {
            return ['valid' => false, 'reason' => 'This promo code is not valid for this event.'];
        }

        // Check ticket types restriction
        if ($ticketType !== null && $this->ticketTypes()->exists() && !$this->ticketTypes()->where('ems_ticket_types.id', $ticketType->id)->exists()) {
            return ['valid' => false, 'reason' => 'This promo code is not valid for the selected ticket type.'];
        }

        // Check overall usage limit
        if ($this->usage_limit !== null) {
            // Count completed registrations/orders referencing this code
            $usedCount = $this->registrations()->whereIn('status', ['confirmed', 'pending', 'awaiting_payment'])->count();
            if ($usedCount >= $this->usage_limit) {
                return ['valid' => false, 'reason' => 'This promo code usage limit has been reached.'];
            }
        }

        // Check usage per attendee limit
        if ($this->usage_per_attendee !== null) {
            $userQuery = $this->registrations()->whereIn('status', ['confirmed', 'pending', 'awaiting_payment']);
            if ($user !== null) {
                $userUsed = (clone $userQuery)->where('user_id', $user->id)->count();
                if ($userUsed >= $this->usage_per_attendee) {
                    return ['valid' => false, 'reason' => 'You have reached the usage limit for this promo code.'];
                }
            } elseif ($email !== null) {
                $emailUsed = (clone $userQuery)->where('attendee_email', strtolower($email))->count();
                if ($emailUsed >= $this->usage_per_attendee) {
                    return ['valid' => false, 'reason' => 'This email has reached the usage limit for this promo code.'];
                }
            }
        }

        return ['valid' => true, 'reason' => 'Promo code is valid.'];
    }

    public function calculateDiscount(float $price): float
    {
        if ($this->discount_type === 'percentage') {
            return round($price * ((float) $this->discount_value / 100), 2);
        }

        if ($this->discount_type === 'fixed') {
            return min($price, (float) $this->discount_value);
        }

        if ($this->discount_type === 'free') {
            return $price;
        }

        return 0.0;
    }
}
