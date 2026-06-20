<?php

namespace App\Services;

use App\Models\User;
use App\Models\MentorAssignment;
use App\Models\Enrollment;
use App\Models\Progress;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;
use Exception;

class MentorAssignmentService
{
    protected $maxCapacity = 15;

    public function getMentors(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = User::whereHas('roles', function ($q) {
            $q->where('slug', 'mentor');
        })->withCount(['students' => function ($q) {
            $q->where('mentor_assignments.status', 'active');
        }]);

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('email', 'like', '%' . $filters['search'] . '%');
            });
        }

        return $query->latest()->paginate($perPage);
    }

    public function getMentorProfile(User $mentor): array
    {
        $mentor->load(['students.enrollments.course']);
        
        $students = $mentor->students->map(function ($student) {
            $enrollmentsCount = $student->enrollments->count();
            $completedCount = $student->enrollments->where('status', 'completed')->count();

            return [
                'id' => $student->id,
                'uuid' => $student->uuid,
                'name' => $student->name,
                'email' => $student->email,
                'status' => $student->pivot->status,
                'assigned_at' => $student->pivot->created_at,
                'notes' => $student->pivot->notes,
                'enrollments_count' => $enrollmentsCount,
                'completed_count' => $completedCount,
            ];
        });

        // Compute aggregate engagement metrics
        $totalLessonsCompleted = Progress::whereIn('user_id', $mentor->students->pluck('id'))
            ->where('completed', true)
            ->count();

        return [
            'id' => $mentor->id,
            'name' => $mentor->name,
            'email' => $mentor->email,
            'avatar' => $mentor->avatar,
            'students' => $students,
            'capacity' => $this->maxCapacity,
            'assigned_count' => $students->where('status', 'active')->count(),
            'engagement_metrics' => [
                'total_lessons_completed' => $totalLessonsCompleted,
                'active_students' => $students->where('status', 'active')->count()
            ]
        ];
    }

    public function assignMentor(int $mentorId, int $studentId, int $assignedBy = null, string $notes = null): MentorAssignment
    {
        // Verify capacity
        $currentCount = MentorAssignment::where('mentor_id', $mentorId)->where('status', 'active')->count();
        if ($currentCount >= $this->maxCapacity) {
            throw new Exception("Mentor has reached maximum capacity of {$this->maxCapacity} students.");
        }

        // Verify if assignment already exists
        $existing = MentorAssignment::where('student_id', $studentId)
            ->where('status', 'active')
            ->first();

        if ($existing) {
            if ($existing->mentor_id === $mentorId) {
                return $existing;
            }
            // If assigned to another, deactivate old one
            $existing->update(['status' => 'inactive']);
        }

        return MentorAssignment::create([
            'mentor_id' => $mentorId,
            'student_id' => $studentId,
            'assigned_by' => $assignedBy,
            'status' => 'active',
            'notes' => $notes,
        ]);
    }

    public function removeAssignment(int $mentorId, int $studentId): bool
    {
        return MentorAssignment::where('mentor_id', $mentorId)
            ->where('student_id', $studentId)
            ->delete();
    }

    public function reassignMentor(int $studentId, int $newMentorId, int $assignedBy = null): MentorAssignment
    {
        return DB::transaction(function () use ($studentId, $newMentorId, $assignedBy) {
            MentorAssignment::where('student_id', $studentId)
                ->where('status', 'active')
                ->update(['status' => 'inactive']);

            return $this->assignMentor($newMentorId, $studentId, $assignedBy, "Reassigned.");
        });
    }

    public function bulkAssign(int $mentorId, array $studentIds, int $assignedBy = null): array
    {
        $success = [];
        $failed = [];

        foreach ($studentIds as $studentId) {
            try {
                $this->assignMentor($mentorId, $studentId, $assignedBy);
                $success[] = $studentId;
            } catch (Exception $e) {
                $failed[] = [
                    'student_id' => $studentId,
                    'error' => $e->getMessage()
                ];
            }
        }

        return [
            'success' => $success,
            'failed' => $failed
        ];
    }
}
