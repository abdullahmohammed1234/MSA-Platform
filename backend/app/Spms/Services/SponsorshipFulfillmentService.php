<?php

namespace App\Spms\Services;

use App\Models\User;
use App\Services\Security\AuditLogger;
use App\Spms\Models\Deliverable;
use App\Spms\Models\Fulfillment;
use App\Spms\Models\Sponsorship;
use Illuminate\Support\Facades\DB;

class SponsorshipFulfillmentService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function addDeliverable(Sponsorship $sponsorship, array $data): Deliverable
    {
        return DB::transaction(function () use ($sponsorship, $data) {
            $deliverable = $sponsorship->deliverables()->create($data);
            $this->recalculateFulfillmentStatus($sponsorship);
            return $deliverable;
        });
    }

    public function completeFulfillment(Deliverable $deliverable, array $data, User $completer): Fulfillment
    {
        return DB::transaction(function () use ($deliverable, $data, $completer) {
            $fulfillment = $deliverable->fulfillments()->create([
                'completed_at' => $data['completed_at'] ?? now(),
                'completed_by' => $completer->id,
                'proof_url' => $data['proof_url'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $deliverable->update(['status' => 'completed']);

            $this->recalculateFulfillmentStatus($deliverable->sponsorship);

            $this->auditLogger->log(
                'spms.deliverable.fulfilled',
                'spms_deliverable',
                (string) $deliverable->id,
                [
                    'title' => $deliverable->title,
                    'proof_url' => $fulfillment->proof_url,
                ],
                $completer
            );

            return $fulfillment;
        });
    }

    public function recalculateFulfillmentStatus(Sponsorship $sponsorship): void
    {
        $total = $sponsorship->deliverables()->count();
        if ($total === 0) {
            $sponsorship->update(['fulfillment_status' => 'not_started']);
            return;
        }

        $completed = $sponsorship->deliverables()->where('status', 'completed')->count();

        if ($completed === $total) {
            $sponsorship->update(['fulfillment_status' => 'fulfilled']);
        } elseif ($completed > 0) {
            $sponsorship->update(['fulfillment_status' => 'partially_fulfilled']);
        } else {
            $sponsorship->update(['fulfillment_status' => 'not_started']);
        }
    }
}
