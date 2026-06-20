<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Traits\HasRolesAndPermissions;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Str;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRolesAndPermissions, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'uuid',
        'avatar',
        'is_active',
        'last_login_at',
        'academy_onboarding_completed_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'academy_onboarding_completed_at' => 'datetime',
        ];
    }

    /**
     * Boot the model to handle automatic UUID creation.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($user) {
            if (empty($user->uuid)) {
                $user->uuid = (string) Str::uuid();
            }
        });

        static::created(function ($user) {
            $user->notificationPreferences()->create([
                'course_completion' => true,
                'new_announcements' => true,
                'upcoming_training' => true,
                'certificate_earned' => true,
                'email_enabled' => true,
                'in_app_enabled' => true,
            ]);
        });
    }

    /**
     * Get the enrollments for the user.
     */
    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    /**
     * Get the progress records for the user.
     */
    public function progressRecords()
    {
        return $this->hasMany(Progress::class);
    }

    /**
     * Get the certificate awards earned by the user.
     */
    public function certificateAwards()
    {
        return $this->hasMany(CertificateAward::class);
    }

    /**
     * Alias for backward compatibility.
     */
    public function certificates()
    {
        return $this->hasMany(CertificateAward::class);
    }

    /**
     * Get the achievements unlocked by the user.
     */
    public function achievementAwards()
    {
        return $this->hasMany(AchievementAward::class);
    }

    /**
     * Get the badges awarded to the user.
     */
    public function badgeAwards()
    {
        return $this->hasMany(BadgeAward::class);
    }

    /**
     * Get the milestones awarded to the user.
     */
    public function milestoneAwards()
    {
        return $this->hasMany(MilestoneAward::class);
    }

    /**
     * Get the quiz attempts made by the user.
     */
    public function quizAttempts()
    {
        return $this->hasMany(QuizAttempt::class);
    }

    /**
     * Get the students assigned to this mentor.
     */
    public function students()
    {
        return $this->belongsToMany(User::class, 'mentor_assignments', 'mentor_id', 'student_id')
            ->withPivot(['id', 'assigned_by', 'status', 'notes', 'created_at'])
            ->withTimestamps();
    }

    /**
     * Get the mentors assigned to this student.
     */
    public function mentors()
    {
        return $this->belongsToMany(User::class, 'mentor_assignments', 'student_id', 'mentor_id')
            ->withPivot(['id', 'assigned_by', 'status', 'notes', 'created_at'])
            ->withTimestamps();
    }

    /**
     * Get the mentor assignments for the user.
     */
    public function mentorAssignments()
    {
        return $this->hasMany(MentorAssignment::class, 'student_id');
    }

    /**
     * Get the student assignments for the user.
     */
    public function studentAssignments()
    {
        return $this->hasMany(MentorAssignment::class, 'mentor_id');
    }

    /**
     * Get the notifications for the user.
     */
    public function customNotifications()
    {
        return $this->hasMany(Notification::class)->latest();
    }

    /**
     * Get the notification preferences for the user.
     */
    public function notificationPreferences()
    {
        return $this->hasOne(NotificationPreference::class);
    }

    /**
     * Get the notification logs for the user.
     */
    public function notificationLogs()
    {
        return $this->hasMany(NotificationLog::class);
    }
}

