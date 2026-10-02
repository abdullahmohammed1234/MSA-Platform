<?php

namespace App\Volunteering\Services;

use App\Volunteering\Models\Opportunity;
use App\Volunteering\Models\Shift;
use App\Volunteering\Models\Signup;
use App\Volunteering\Models\Team;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class VolunteerSignupService
{
    private readonly VmsNotificationDispatcher $notificationDispatcher;

    public function __construct(
        ?VmsNotificationDispatcher $notificationDispatcher = null
    ) {
        $this->notificationDispatcher = $notificationDispatcher ?? app(VmsNotificationDispatcher::class);
    }

    public function registerSignup(array $data, ?int $userId = null): Signup
    {
        return DB::transaction(function () use ($data, $userId) {
            $opportunity = Opportunity::where('id', $data['opportunity_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if ($opportunity->status !== 'open') {
                throw ValidationException::withMessages([
                    'opportunity_id' => ['This volunteer opportunity is currently not accepting signups.'],
                ]);
            }

            if ($opportunity->end_at && $opportunity->end_at->isPast()) {
                throw ValidationException::withMessages([
                    'opportunity_id' => ['This volunteer opportunity has already ended.'],
                ]);
            }

            $isFull = false;

            $shift = null;
            if (!empty($data['shift_id'])) {
                $shift = Shift::with('team')->where('id', $data['shift_id'])
                    ->where('opportunity_id', $opportunity->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($shift->status !== 'open') {
                    throw ValidationException::withMessages([
                        'shift_id' => ['The selected shift is currently closed.'],
                    ]);
                }

                if ($shift->end_at && $shift->end_at->isPast()) {
                    throw ValidationException::withMessages([
                        'shift_id' => ['The selected shift has already ended.'],
                    ]);
                }

                if ($shift->team && $shift->team->status !== 'open') {
                    throw ValidationException::withMessages([
                        'shift_id' => ['The team associated with this shift is currently closed.'],
                    ]);
                }

                $activeShiftSignupsCount = Signup::where('shift_id', $shift->id)
                    ->whereIn('status', ['signed_up', 'confirmed', 'completed'])
                    ->lockForUpdate()
                    ->count();

                if ($shift->capacity !== null && $activeShiftSignupsCount >= $shift->capacity) {
                    $isFull = true;
                }
            }

            $team = null;
            if (!empty($data['team_id'])) {
                $team = Team::where('id', $data['team_id'])
                    ->where('opportunity_id', $opportunity->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($team->status !== 'open') {
                    throw ValidationException::withMessages([
                        'team_id' => ['The selected team is currently closed.'],
                    ]);
                }
            }

            // Relationship boundary check: if both team_id and shift_id provided, ensure shift belongs to team
            if ($shift && $team && $shift->team_id && (int) $shift->team_id !== (int) $team->id) {
                throw ValidationException::withMessages([
                    'shift_id' => ['The selected shift does not belong to the selected team.'],
                ]);
            }

            // Derive team from shift if team_id was not explicitly passed
            $targetTeam = $team ?? $shift?->team;
            if ($targetTeam && $targetTeam->capacity !== null) {
                $activeTeamSignupsCount = Signup::where('team_id', $targetTeam->id)
                    ->whereIn('status', ['signed_up', 'confirmed', 'completed'])
                    ->lockForUpdate()
                    ->count();

                if ($activeTeamSignupsCount >= $targetTeam->capacity) {
                    $isFull = true;
                }
            }

            // Check overall opportunity capacity if configured
            if ($opportunity->capacity !== null) {
                $activeOppSignupsCount = Signup::where('opportunity_id', $opportunity->id)
                    ->whereIn('status', ['signed_up', 'confirmed', 'completed'])
                    ->lockForUpdate()
                    ->count();

                if ($activeOppSignupsCount >= $opportunity->capacity) {
                    $isFull = true;
                }
            }

            $joinWaitlistRequested = (bool) ($data['join_waitlist'] ?? false);

            if ($isFull && !$joinWaitlistRequested) {
                throw ValidationException::withMessages([
                    'shift_id' => ['The selected shift or opportunity has reached maximum capacity.'],
                ]);
            }

            // Prevent duplicate active signups for same email + shift/opportunity
            $email = strtolower(trim($data['email']));
            $existingQuery = Signup::where('opportunity_id', $opportunity->id)
                ->where('email', $email)
                ->whereIn('status', ['signed_up', 'confirmed', 'waitlisted']);

            if ($shift) {
                $existingQuery->where('shift_id', $shift->id);
            } else {
                $existingQuery->whereNull('shift_id');
            }

            if ($existingQuery->exists()) {
                throw ValidationException::withMessages([
                    'email' => ['You are already registered for this shift/opportunity.'],
                ]);
            }

            // Link user_id if not explicitly provided but email matches a registered user account
            if (!$userId && !empty($data['email'])) {
                $matchedUser = \App\Models\User::where('email', strtolower(trim($data['email'])))->first();
                if ($matchedUser) {
                    $userId = $matchedUser->id;
                }
            }

            $status = $isFull ? 'waitlisted' : 'signed_up';

            $signup = Signup::create([
                'uuid' => (string) Str::uuid(),
                'opportunity_id' => $opportunity->id,
                'team_id' => $targetTeam?->id,
                'shift_id' => $shift?->id,
                'user_id' => $userId,
                'name' => $data['name'],
                'email' => $email,
                'phone' => $data['phone'] ?? null,
                'experience' => $data['experience'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => $status,
            ]);

            $signup->load(['opportunity.event', 'team', 'shift']);

            DB::afterCommit(function () use ($signup, $status) {
                if ($status === 'waitlisted') {
                    $this->notificationDispatcher->notifyWaitlistJoined($signup);
                } else {
                    $this->notificationDispatcher->notifySignupConfirmed($signup);
                    $this->notificationDispatcher->notifyAdminSignupReceived($signup);
                }
            });

            return $signup;
        });
    }

    public function cancelSignup(Signup $signup, ?int $userId = null): Signup
    {
        return DB::transaction(function () use ($signup) {
            $lockedSignup = Signup::where('id', $signup->id)->lockForUpdate()->firstOrFail();

            if ($lockedSignup->status === 'cancelled') {
                return $lockedSignup;
            }

            $lockedSignup->update([
                'status' => 'cancelled',
            ]);

            $promoted = $this->promoteNextWaitlistedVolunteer($lockedSignup);

            DB::afterCommit(function () use ($lockedSignup, $promoted) {
                $this->notificationDispatcher->notifySignupCancelled($lockedSignup);
                $this->notificationDispatcher->notifyAdminSignupCancelled($lockedSignup);

                if ($promoted) {
                    $this->notificationDispatcher->notifyWaitlistPromoted($promoted);
                }
            });

            return $lockedSignup->fresh();
        });
    }

    public function updateStatus(Signup $signup, string $status, ?string $adminNotes = null, ?int $adminId = null): Signup
    {
        $validStatuses = ['signed_up', 'confirmed', 'cancelled', 'completed', 'no_show', 'waitlisted'];
        if (!in_array($status, $validStatuses, true)) {
            throw ValidationException::withMessages([
                'status' => ['Invalid volunteer status.'],
            ]);
        }

        return DB::transaction(function () use ($signup, $status, $adminNotes, $adminId) {
            $lockedSignup = Signup::where('id', $signup->id)->lockForUpdate()->firstOrFail();
            $oldStatus = $lockedSignup->status;

            if ($oldStatus === $status) {
                if ($adminNotes !== null || $adminId !== null) {
                    $lockedSignup->update([
                        'admin_notes' => $adminNotes ?? $lockedSignup->admin_notes,
                        'processed_by' => $adminId ?? $lockedSignup->processed_by,
                        'processed_at' => now(),
                    ]);
                }
                return $lockedSignup->fresh();
            }

            $allowedTransitions = [
                'signed_up' => ['confirmed', 'waitlisted', 'cancelled', 'completed'],
                'confirmed' => ['completed', 'no_show', 'cancelled'],
                'waitlisted' => ['signed_up', 'confirmed', 'cancelled'],
                'pending' => ['signed_up', 'confirmed', 'cancelled', 'waitlisted'],
                'completed' => [],
                'cancelled' => [],
                'no_show' => [],
            ];

            $allowed = $allowedTransitions[$oldStatus] ?? [];
            if (!in_array($status, $allowed, true)) {
                throw ValidationException::withMessages([
                    'status' => ["Cannot transition signup status from {$oldStatus} to {$status}."],
                ]);
            }

            $lockedSignup->update([
                'status' => $status,
                'admin_notes' => $adminNotes ?? $lockedSignup->admin_notes,
                'processed_by' => $adminId ?? $lockedSignup->processed_by,
                'processed_at' => now(),
            ]);

            $promoted = null;
            if ($status === 'cancelled') {
                $promoted = $this->promoteNextWaitlistedVolunteer($lockedSignup);
            }

            DB::afterCommit(function () use ($lockedSignup, $oldStatus, $status, $promoted) {
                if (($oldStatus === 'waitlisted' || $oldStatus === 'pending') && in_array($status, ['signed_up', 'confirmed'], true)) {
                    $this->notificationDispatcher->notifyWaitlistPromoted($lockedSignup);
                } elseif ($status === 'cancelled') {
                    $this->notificationDispatcher->notifySignupCancelled($lockedSignup);
                    $this->notificationDispatcher->notifyAdminSignupCancelled($lockedSignup);
                    if ($promoted) {
                        $this->notificationDispatcher->notifyWaitlistPromoted($promoted);
                    }
                } elseif ($status === 'confirmed' && $oldStatus !== 'confirmed') {
                    $this->notificationDispatcher->notifySignupConfirmed($lockedSignup);
                }

                if ($lockedSignup->user) {
                    app(VolunteerAchievementService::class)->evaluateUserAchievements($lockedSignup->user);
                }
            });

            return $lockedSignup->fresh();
        });
    }

    /**
     * Promote next waitlisted volunteer for the same shift/opportunity if capacity allows.
     */
    private function promoteNextWaitlistedVolunteer(Signup $cancelledSignup): ?Signup
    {
        $opportunity = Opportunity::where('id', $cancelledSignup->opportunity_id)->lockForUpdate()->first();
        if ($opportunity && $opportunity->capacity !== null) {
            $activeOppCount = Signup::where('opportunity_id', $opportunity->id)
                ->whereIn('status', ['signed_up', 'confirmed', 'completed'])
                ->count();
            if ($activeOppCount >= $opportunity->capacity) {
                return null;
            }
        }

        if ($cancelledSignup->shift_id) {
            $shift = Shift::where('id', $cancelledSignup->shift_id)->lockForUpdate()->first();
            if ($shift && $shift->capacity !== null) {
                $activeShiftCount = Signup::where('shift_id', $shift->id)
                    ->whereIn('status', ['signed_up', 'confirmed', 'completed'])
                    ->count();
                if ($activeShiftCount >= $shift->capacity) {
                    return null;
                }
            }
        }

        if ($cancelledSignup->team_id) {
            $team = Team::where('id', $cancelledSignup->team_id)->lockForUpdate()->first();
            if ($team && $team->capacity !== null) {
                $activeTeamCount = Signup::where('team_id', $team->id)
                    ->whereIn('status', ['signed_up', 'confirmed', 'completed'])
                    ->count();
                if ($activeTeamCount >= $team->capacity) {
                    return null;
                }
            }
        }

        $query = Signup::where('opportunity_id', $cancelledSignup->opportunity_id)
            ->where('status', 'waitlisted');

        if ($cancelledSignup->shift_id) {
            $query->where('shift_id', $cancelledSignup->shift_id);
        } elseif ($cancelledSignup->team_id) {
            $query->where('team_id', $cancelledSignup->team_id);
        }

        $nextWaitlisted = $query->orderBy('created_at', 'asc')
            ->lockForUpdate()
            ->first();

        if ($nextWaitlisted !== null) {
            $nextWaitlisted->update([
                'status' => 'signed_up',
                'processed_at' => now(),
            ]);

            return $nextWaitlisted->fresh(['opportunity.event', 'team', 'shift']);
        }

        return null;
    }

    public function listAllSignups(array $filters = []): \Illuminate\Pagination\LengthAwarePaginator
    {
        $query = Signup::with([
            'opportunity:id,uuid,title,slug,event_id,start_at,end_at,location,status',
            'opportunity.event:id,name,slug',
            'team:id,name,capacity,status',
            'shift:id,name,start_at,end_at,capacity,status',
            'user:id,name,email',
            'processor:id,name',
        ]);

        if (!empty($filters['opportunity_id'])) {
            $query->where('opportunity_id', $filters['opportunity_id']);
        }

        if (!empty($filters['team_id'])) {
            $query->where('team_id', $filters['team_id']);
        }

        if (!empty($filters['shift_id'])) {
            $query->where('shift_id', $filters['shift_id']);
        }

        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['attendance_status']) && $filters['attendance_status'] !== 'all') {
            $query->where('attendance_status', $filters['attendance_status']);
        }

        if (!empty($filters['search'])) {
            $search = '%' . trim($filters['search']) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', $search)
                  ->orWhere('email', 'like', $search)
                  ->orWhere('phone', 'like', $search)
                  ->orWhereHas('opportunity', function ($oq) use ($search) {
                      $oq->where('title', 'like', $search);
                  });
            });
        }

        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = strtolower($filters['sort_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $allowedSorts = ['created_at', 'name', 'email', 'status', 'attendance_status', 'attended_at'];

        if (in_array($sortBy, $allowedSorts, true)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $perPage = min(max((int) ($filters['per_page'] ?? 15), 1), 100);

        return $query->paginate($perPage);
    }

    public function updateAttendance(
        Signup $signup,
        string $attendanceStatus,
        ?string $adminNotes = null,
        ?int $adminId = null
    ): Signup {
        $validAttendance = ['not_marked', 'present', 'absent', 'excused'];
        if (!in_array($attendanceStatus, $validAttendance, true)) {
            throw ValidationException::withMessages([
                'attendance_status' => ['Invalid attendance status.'],
            ]);
        }

        if ($signup->status === 'cancelled') {
            throw ValidationException::withMessages([
                'attendance_status' => ['Cannot update attendance for a cancelled signup.'],
            ]);
        }

        return DB::transaction(function () use ($signup, $attendanceStatus, $adminNotes, $adminId) {
            $attendedAt = $attendanceStatus === 'present'
                ? ($signup->attended_at ?? now())
                : ($attendanceStatus === 'not_marked' ? null : $signup->attended_at);

            $updates = [
                'attendance_status' => $attendanceStatus,
                'attended_at' => $attendedAt,
                'admin_notes' => $adminNotes !== null ? $adminNotes : $signup->admin_notes,
                'processed_by' => $adminId ?? $signup->processed_by,
                'processed_at' => now(),
            ];

            // If marked present and status is still signed_up, elevate to confirmed
            if ($attendanceStatus === 'present' && $signup->status === 'signed_up') {
                $updates['status'] = 'confirmed';
            } elseif ($attendanceStatus === 'absent' && in_array($signup->status, ['signed_up', 'confirmed'], true)) {
                $updates['status'] = 'no_show';
            }

            $signup->update($updates);

            if ($signup->user) {
                DB::afterCommit(function () use ($signup) {
                    app(VolunteerAchievementService::class)->evaluateUserAchievements($signup->user);
                });
            }

            return $signup->fresh(['opportunity.event', 'team', 'shift', 'user', 'processor']);
        });
    }

    public function batchAttendance(
        array $signupIds,
        string $attendanceStatus,
        ?string $adminNotes = null,
        ?int $adminId = null
    ): int {
        $validAttendance = ['not_marked', 'present', 'absent', 'excused'];
        if (!in_array($attendanceStatus, $validAttendance, true)) {
            throw ValidationException::withMessages([
                'attendance_status' => ['Invalid attendance status.'],
            ]);
        }

        return DB::transaction(function () use ($signupIds, $attendanceStatus, $adminNotes, $adminId) {
            $signups = Signup::whereIn('id', $signupIds)->lockForUpdate()->get();
            $count = 0;

            foreach ($signups as $signup) {
                $this->updateAttendance($signup, $attendanceStatus, $adminNotes, $adminId);
                $count++;
            }

            return $count;
        });
    }

    public function promoteWaitlistedSignup(Signup $signup, ?int $adminId = null): Signup
    {
        if ($signup->status !== 'waitlisted') {
            throw ValidationException::withMessages([
                'status' => ['Only waitlisted volunteers can be promoted.'],
            ]);
        }

        return DB::transaction(function () use ($signup, $adminId) {
            $lockedSignup = Signup::where('id', $signup->id)->lockForUpdate()->firstOrFail();

            $lockedSignup->update([
                'status' => 'confirmed',
                'processed_by' => $adminId ?? $lockedSignup->processed_by,
                'processed_at' => now(),
            ]);

            DB::afterCommit(function () use ($lockedSignup) {
                $this->notificationDispatcher->notifyWaitlistPromoted($lockedSignup);
            });

            return $lockedSignup->fresh(['opportunity.event', 'team', 'shift', 'user']);
        });
    }

    public function exportSignupsCsv(array $filters = []): string
    {
        $filters['per_page'] = 1000;
        $signups = $this->listAllSignups($filters);

        $output = fopen('php://temp', 'r+');

        // CSV Header
        fputcsv($output, [
            'Signup UUID',
            'Volunteer Name',
            'Volunteer Email',
            'Phone',
            'Opportunity',
            'Team',
            'Shift',
            'Status',
            'Attendance Status',
            'Attended At',
            'Admin Notes',
            'Signup Date',
        ]);

        foreach ($signups->items() as $signup) {
            // Formula injection protection for CSV
            $sanitize = function (?string $val): string {
                if ($val === null) return '';
                $val = trim($val);
                if (in_array(substr($val, 0, 1), ['=', '+', '-', '@'], true)) {
                    return "'" . $val;
                }
                return $val;
            };

            fputcsv($output, [
                $signup->uuid,
                $sanitize($signup->name),
                $sanitize($signup->email),
                $sanitize($signup->phone),
                $sanitize($signup->opportunity?->title),
                $sanitize($signup->team?->name ?? 'General'),
                $sanitize($signup->shift?->name ?? 'General Shift'),
                $signup->status,
                $signup->attendance_status ?? 'not_marked',
                $signup->attended_at ? $signup->attended_at->toIso8601String() : '',
                $sanitize($signup->admin_notes),
                $signup->created_at ? $signup->created_at->toIso8601String() : '',
            ]);
        }

        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);

        return $csvContent !== false ? $csvContent : '';
    }

    public function getUserHistory(int $userId): array
    {
        $user = \App\Models\User::find($userId);
        $userEmail = $user ? strtolower(trim($user->email)) : null;

        $signups = Signup::with(['opportunity:id,title,slug,start_at,end_at,location,status', 'team:id,name', 'shift:id,name,start_at,end_at'])
            ->where(function ($q) use ($userId, $userEmail) {
                $q->where('user_id', $userId);
                if ($userEmail) {
                    $q->orWhere('email', $userEmail);
                }
            })
            ->orderBy('created_at', 'desc')
            ->get();

        $totalHours = 0.0;
        foreach ($signups as $s) {
            $hours = 0.0;
            if ($s->status === 'completed' || ($s->attendance_status === 'present' && $s->status === 'confirmed')) {
                $start = $s->shift?->start_at ?? $s->opportunity?->start_at;
                $end = $s->shift?->end_at ?? $s->opportunity?->end_at;
                if ($start && $end && $end->ne($start)) {
                    $hours = round(abs($start->diffInMinutes($end)) / 60.0, 1);
                }
            }
            $s->service_hours = $hours;
            $totalHours += $hours;
        }

        return [
            'total_signups' => $signups->count(),
            'completed_count' => $signups->where('status', 'completed')->count(),
            'active_count' => $signups->whereIn('status', ['signed_up', 'confirmed'])->count(),
            'total_service_hours' => round($totalHours, 1),
            'signups' => $signups,
        ];
    }
}
