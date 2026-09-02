<?php

namespace App\Services;

use App\Enums\VolunteerRegistrationStatus;
use App\Mail\VolunteerApplication;
use App\Models\VolunteerRegistration;
use App\Services\Security\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class VolunteerRegistrationService
{
    protected AuditLogger $auditLogger;

    public function __construct(AuditLogger $auditLogger)
    {
        $this->auditLogger = $auditLogger;
    }

    /**
     * Store public volunteer submission into database first, then attempt email notification.
     * Email failure MUST NOT roll back or break the database record persistence.
     */
    public function submit(array $data): VolunteerRegistration
    {
        /** @var VolunteerRegistration $registration */
        $registration = DB::transaction(function () use ($data) {
            return VolunteerRegistration::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'student_number' => $data['student_number'],
                'department' => $data['department'],
                'interests' => $data['interests'],
                'experience' => $data['experience'] ?? null,
                'status' => VolunteerRegistrationStatus::New,
            ]);
        });

        // Audit Log entry for creation
        $this->auditLogger->log(
            'volunteer_registration.created',
            $registration,
            "New volunteer application received from {$registration->name} ({$registration->email})",
            [
                'email' => $registration->email,
                'department' => $registration->department,
                'student_number' => $registration->student_number,
            ]
        );

        // Attempt sending notification email
        try {
            $recipient = config('website.contact_recipient');
            if (!empty($recipient)) {
                Mail::to($recipient)->send(new VolunteerApplication(
                    $registration->name,
                    $registration->email,
                    $registration->student_number,
                    $registration->department,
                    $registration->interests,
                    $registration->experience
                ));
            }
        } catch (Throwable $exception) {
            Log::error('Volunteer application notification email failed', [
                'registration_id' => $registration->id,
                'registration_uuid' => $registration->uuid,
                'applicant_email' => $registration->email,
                'error' => $exception->getMessage(),
            ]);
        }

        return $registration;
    }

    /**
     * Update administrative fields on a volunteer registration.
     */
    public function update(VolunteerRegistration $registration, array $data, ?int $adminUserId = null): VolunteerRegistration
    {
        $oldStatus = $registration->status;

        if (isset($data['status'])) {
            $newStatus = $data['status'] instanceof VolunteerRegistrationStatus
                ? $data['status']
                : VolunteerRegistrationStatus::from($data['status']);

            $registration->status = $newStatus;

            if ($oldStatus !== $newStatus) {
                if ($newStatus === VolunteerRegistrationStatus::Contacted && $registration->contacted_at === null) {
                    $registration->contacted_at = now();
                }

                if (in_array($newStatus, [
                    VolunteerRegistrationStatus::Accepted,
                    VolunteerRegistrationStatus::Rejected,
                    VolunteerRegistrationStatus::Archived,
                ], true) && $registration->processed_at === null) {
                    $registration->processed_at = now();
                }

                $this->auditLogger->log(
                    'volunteer_registration.status_updated',
                    $registration,
                    "Status changed from '{$oldStatus->value}' to '{$newStatus->value}'",
                    ['old_status' => $oldStatus->value, 'new_status' => $newStatus->value],
                    $adminUserId
                );
            }
        }

        if (array_key_exists('admin_notes', $data) && $data['admin_notes'] !== $registration->admin_notes) {
            $registration->admin_notes = $data['admin_notes'];

            $this->auditLogger->log(
                'volunteer_registration.notes_updated',
                $registration,
                'Administrative notes updated',
                ['notes_length' => strlen((string) $data['admin_notes'])],
                $adminUserId
            );
        }

        if (array_key_exists('assigned_to', $data) && (int) $data['assigned_to'] !== (int) $registration->assigned_to) {
            $oldAssignee = $registration->assigned_to;
            $registration->assigned_to = $data['assigned_to'];

            $this->auditLogger->log(
                'volunteer_registration.assignment_updated',
                $registration,
                "Assigned administrator changed from ID '{$oldAssignee}' to '{$data['assigned_to']}'",
                ['old_assigned_to' => $oldAssignee, 'new_assigned_to' => $data['assigned_to']],
                $adminUserId
            );
        }

        $registration->save();

        return $registration->fresh(['assignedTo']);
    }

    /**
     * Soft delete / archive a volunteer registration.
     */
    public function delete(VolunteerRegistration $registration, ?int $adminUserId = null): void
    {
        $this->auditLogger->log(
            'volunteer_registration.deleted',
            $registration,
            "Volunteer registration for {$registration->name} was soft-deleted/archived.",
            ['uuid' => $registration->uuid],
            $adminUserId
        );

        $registration->delete();
    }
}
