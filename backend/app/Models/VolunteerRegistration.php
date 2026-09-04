<?php

namespace App\Models;

use App\Enums\VolunteerRegistrationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class VolunteerRegistration extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'volunteer_registrations';

    protected $fillable = [
        'uuid',
        'name',
        'email',
        'phone',
        'student_number',
        'department',
        'interests',
        'experience',
        'status',
        'admin_notes',
        'assigned_to',
        'contacted_at',
        'processed_at',
    ];

    protected $casts = [
        'status' => VolunteerRegistrationStatus::class,
        'contacted_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }

            if (empty($model->status)) {
                $model->status = VolunteerRegistrationStatus::New;
            }
        });
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
