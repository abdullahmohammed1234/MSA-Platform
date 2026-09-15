<?php

namespace App\Services\Operations\Rules;

use App\Enums\VolunteerRegistrationStatus;
use App\Models\VolunteerRegistration;
use App\Services\Operations\OperationalAlertService;
use Illuminate\Support\Facades\Schema;

class VolunteerOperationalRules
{
    public function __construct(
        private OperationalAlertService $alertService
    ) {}

    public function detect(): int
    {
        if (! Schema::hasTable('volunteer_registrations')) {
            return 0;
        }

        $detected = 0;

        $stalePendingCount = VolunteerRegistration::where('status', VolunteerRegistrationStatus::New->value)
            ->where('created_at', '<', now()->subHours(72))
            ->count();

        if ($stalePendingCount > 5) {
            $this->alertService->upsertAlert([
                'category' => 'volunteering',
                'severity' => 'medium',
                'title' => "Volunteer Registration Backlog ({$stalePendingCount} Pending)",
                'description' => "There are {$stalePendingCount} unreviewed volunteer applications submitted more than 72 hours ago.",
                'source_type' => 'System',
                'source_id' => 'volunteer_pending_backlog_summary',
                'rule_key' => 'volunteer_pending_backlog',
                'action_url' => '/admin/volunteers',
                'metadata' => [
                    'pending_count' => $stalePendingCount,
                    'threshold' => 5,
                ],
            ]);
            $detected++;
        }

        return $detected;
    }
}
