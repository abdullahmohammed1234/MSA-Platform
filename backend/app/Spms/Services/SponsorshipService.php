<?php

namespace App\Spms\Services;

use App\Models\User;
use App\Services\Security\AuditLogger;
use App\Spms\Models\Agreement;
use App\Spms\Models\Package;
use App\Spms\Models\Sponsorship;
use Illuminate\Support\Facades\DB;

class SponsorshipService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function createSponsorship(array $data, ?User $actor = null): Sponsorship
    {
        return DB::transaction(function () use ($data, $actor) {
            if ($actor !== null) {
                $data['created_by'] = $actor->id;
            }

            // Lock package for updates if max_available limit exists
            if (!empty($data['package_id'])) {
                $package = Package::where('id', $data['package_id'])->lockForUpdate()->first();
                if ($package && $package->max_available !== null) {
                    if ($package->claimed_count >= $package->max_available) {
                        throw new \RuntimeException("Package '{$package->name}' is fully claimed.");
                    }
                    $package->increment('claimed_count');
                }
            }

            $sponsorship = Sponsorship::create($data);

            // Inherit package benefits as initial deliverables if package selected
            if (!empty($sponsorship->package_id)) {
                $package = Package::with('benefits')->find($sponsorship->package_id);
                if ($package) {
                    foreach ($package->benefits as $benefit) {
                        $sponsorship->deliverables()->create([
                            'title' => $benefit->title,
                            'description' => $benefit->description,
                            'deliverable_type' => $benefit->deliverable_type,
                            'due_date' => $sponsorship->start_date,
                            'status' => 'not_started',
                        ]);
                    }
                }
            }

            // Generate initial cash commitment if total_committed_cents is provided
            if ($sponsorship->total_committed_cents > 0) {
                $sponsorship->commitments()->create([
                    'commitment_type' => 'cash',
                    'amount_cents' => $sponsorship->total_committed_cents,
                    'due_date' => $sponsorship->start_date,
                    'status' => 'pending',
                ]);
                $sponsorship->update(['financial_status' => 'committed']);
            }

            if ($actor !== null) {
                $this->auditLogger->log(
                    'spms.sponsorship.created',
                    'spms_sponsorship',
                    (string) $sponsorship->id,
                    [
                        'sponsorship_number' => $sponsorship->sponsorship_number,
                        'total_committed_cents' => $sponsorship->total_committed_cents,
                        'status' => $sponsorship->status->value,
                    ],
                    $actor
                );
            }

            return $sponsorship;
        });
    }

    public function updateStatus(Sponsorship $sponsorship, string $newStatus, ?User $actor = null): Sponsorship
    {
        return DB::transaction(function () use ($sponsorship, $newStatus, $actor) {
            $oldStatus = $sponsorship->status->value;
            $sponsorship->update(['status' => $newStatus]);

            if ($actor !== null) {
                $this->auditLogger->log(
                    'spms.sponsorship.status_updated',
                    'spms_sponsorship',
                    (string) $sponsorship->id,
                    [
                        'old_status' => $oldStatus,
                        'new_status' => $newStatus,
                    ],
                    $actor
                );
            }

            return $sponsorship;
        });
    }

    public function executeAgreement(Sponsorship $sponsorship, array $agreementData, ?User $actor = null): Agreement
    {
        return DB::transaction(function () use ($sponsorship, $agreementData, $actor) {
            $agreement = $sponsorship->agreement()->updateOrCreate(
                ['sponsorship_id' => $sponsorship->id],
                array_merge($agreementData, [
                    'status' => 'executed',
                    'signed_at' => now(),
                ])
            );

            if ($sponsorship->status->value === 'proposed' || $sponsorship->status->value === 'negotiating') {
                $sponsorship->update(['status' => 'active']);
            }

            if ($actor !== null) {
                $this->auditLogger->log(
                    'spms.agreement.executed',
                    'spms_agreement',
                    (string) $agreement->id,
                    [
                        'agreement_number' => $agreement->agreement_number,
                        'sponsorship_id' => $sponsorship->id,
                    ],
                    $actor
                );
            }

            return $agreement;
        });
    }
}
