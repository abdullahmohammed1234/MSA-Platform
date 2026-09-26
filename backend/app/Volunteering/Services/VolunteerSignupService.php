<?php

namespace App\Volunteering\Services;

use App\Volunteering\Models\Opportunity;
use App\Volunteering\Models\Shift;
use App\Volunteering\Models\Signup;
use App\Volunteering\Models\Team;
use Illuminate\Support\Facades\DB;
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

                if ($team->capacity !== null) {
                    $activeTeamSignupsCount = Signup::where('team_id', $team->id)
                        ->whereIn('status', ['signed_up', 'confirmed', 'completed'])
                        ->lockForUpdate()
                        ->count();

                    if ($activeTeamSignupsCount >= $team->capacity) {
                        $isFull = true;
                    }
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

            // Prevent duplicate active signups for same opportunity + email + shift
            $email = strtolower(trim($data['email']));
            $existingQuery = Signup::where('opportunity_id', $opportunity->id)
                ->where('email', $email)
                ->whereIn('status', ['signed_up', 'confirmed', 'waitlisted']);

            if ($shift) {
                $existingQuery->where('shift_id', $shift->id);
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
                'opportunity_id' => $opportunity->id,
                'team_id' => $team?->id ?? $shift?->team_id,
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
            $signup->update([
                'status' => 'cancelled',
            ]);

            $promoted = $this->promoteNextWaitlistedVolunteer($signup);

            DB::afterCommit(function () use ($signup, $promoted) {
                $this->notificationDispatcher->notifySignupCancelled($signup);
                $this->notificationDispatcher->notifyAdminSignupCancelled($signup);

                if ($promoted) {
                    $this->notificationDispatcher->notifyWaitlistPromoted($promoted);
                }
            });

            return $signup->fresh();
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

        $oldStatus = $signup->status;

        $signup->update([
            'status' => $status,
            'admin_notes' => $adminNotes ?? $signup->admin_notes,
            'processed_by' => $adminId ?? $signup->processed_by,
            'processed_at' => now(),
        ]);

        $promoted = null;
        if ($status === 'cancelled') {
            $promoted = $this->promoteNextWaitlistedVolunteer($signup);
        }

        DB::afterCommit(function () use ($signup, $oldStatus, $status, $promoted) {
            if (($oldStatus === 'waitlisted' || $oldStatus === 'pending') && in_array($status, ['signed_up', 'confirmed'], true)) {
                $this->notificationDispatcher->notifyWaitlistPromoted($signup);
            } elseif ($status === 'cancelled') {
                $this->notificationDispatcher->notifySignupCancelled($signup);
                $this->notificationDispatcher->notifyAdminSignupCancelled($signup);
                if ($promoted) {
                    $this->notificationDispatcher->notifyWaitlistPromoted($promoted);
                }
            } elseif ($status === 'confirmed' && $oldStatus !== 'confirmed') {
                $this->notificationDispatcher->notifySignupConfirmed($signup);
            }
        });

        return $signup->fresh();
    }

    /**
     * Promote next waitlisted volunteer for the same shift/opportunity if capacity allows.
     */
    private function promoteNextWaitlistedVolunteer(Signup $cancelledSignup): ?Signup
    {
        $query = Signup::where('opportunity_id', $cancelledSignup->opportunity_id)
            ->where('status', 'waitlisted');

        if ($cancelledSignup->shift_id) {
            $query->where('shift_id', $cancelledSignup->shift_id);
        } elseif ($cancelledSignup->team_id) {
            $query->where('team_id', $cancelledSignup->team_id);
        }

        $nextWaitlisted = $query->orderBy('created_at', 'asc')->first();

        if ($nextWaitlisted !== null) {
            $nextWaitlisted->update([
                'status' => 'signed_up',
                'processed_at' => now(),
            ]);

            return $nextWaitlisted->fresh(['opportunity.event', 'team', 'shift']);
        }

        return null;
    }

    public function getUserHistory(int $userId): array
    {
        $user = \App\Models\User::find($userId);
        $userEmail = $user ? strtolower(trim($user->email)) : null;

        $signups = Signup::with(['opportunity:id,title,slug,start_at,location', 'team:id,name', 'shift:id,name,start_at,end_at'])
            ->where(function ($q) use ($userId, $userEmail) {
                $q->where('user_id', $userId);
                if ($userEmail) {
                    $q->orWhere('email', $userEmail);
                }
            })
            ->orderBy('created_at', 'desc')
            ->get();

        return [
            'total_signups' => $signups->count(),
            'completed_count' => $signups->where('status', 'completed')->count(),
            'active_count' => $signups->whereIn('status', ['signed_up', 'confirmed'])->count(),
            'signups' => $signups,
        ];
    }
}
