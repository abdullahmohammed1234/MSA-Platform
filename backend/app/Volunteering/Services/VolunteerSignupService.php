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
                    throw ValidationException::withMessages([
                        'shift_id' => ['The selected shift has reached maximum capacity.'],
                    ]);
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
                        throw ValidationException::withMessages([
                            'team_id' => ['The selected team has reached maximum capacity.'],
                        ]);
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
                    throw ValidationException::withMessages([
                        'opportunity_id' => ['This opportunity has reached maximum overall volunteer capacity.'],
                    ]);
                }
            }

            // Prevent duplicate active signups for same opportunity + email + shift
            $email = strtolower(trim($data['email']));
            $existingQuery = Signup::where('opportunity_id', $opportunity->id)
                ->where('email', $email)
                ->whereIn('status', ['signed_up', 'confirmed']);

            if ($shift) {
                $existingQuery->where('shift_id', $shift->id);
            }

            if ($existingQuery->exists()) {
                throw ValidationException::withMessages([
                    'email' => ['You are already registered for this shift/opportunity.'],
                ]);
            }

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
                'status' => 'signed_up',
            ]);

            return $signup->load(['opportunity', 'team', 'shift']);
        });
    }

    public function cancelSignup(Signup $signup, ?int $userId = null): Signup
    {
        return DB::transaction(function () use ($signup) {
            $signup->update([
                'status' => 'cancelled',
            ]);

            return $signup->fresh();
        });
    }

    public function updateStatus(Signup $signup, string $status, ?string $adminNotes = null, ?int $adminId = null): Signup
    {
        $validStatuses = ['signed_up', 'confirmed', 'cancelled', 'completed', 'no_show'];
        if (!in_array($status, $validStatuses, true)) {
            throw ValidationException::withMessages([
                'status' => ['Invalid volunteer status.'],
            ]);
        }

        $signup->update([
            'status' => $status,
            'admin_notes' => $adminNotes ?? $signup->admin_notes,
            'processed_by' => $adminId ?? $signup->processed_by,
            'processed_at' => now(),
        ]);

        return $signup->fresh();
    }

    public function getUserHistory(int $userId): array
    {
        $signups = Signup::with(['opportunity:id,title,slug,start_at,location', 'team:id,name', 'shift:id,name,start_at,end_at'])
            ->where('user_id', $userId)
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
