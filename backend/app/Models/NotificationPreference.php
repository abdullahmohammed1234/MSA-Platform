<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationPreference extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'course_completion',
        'new_announcements',
        'upcoming_training',
        'certificate_earned',
        'support_updates',
        'direct_messages',
        'email_enabled',
        'in_app_enabled',
        'push_enabled',
    ];

    protected $casts = [
        'course_completion' => 'boolean',
        'new_announcements' => 'boolean',
        'upcoming_training' => 'boolean',
        'certificate_earned' => 'boolean',
        'support_updates' => 'boolean',
        'direct_messages' => 'boolean',
        'email_enabled' => 'boolean',
        'in_app_enabled' => 'boolean',
        'push_enabled' => 'boolean',
    ];

    /**
     * Get the user who owns these preferences.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
