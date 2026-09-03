<?php

namespace App\Spms\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Spms\Models\InKindContribution;
use App\Spms\Models\Organization;
use App\Spms\Models\Payment;
use App\Spms\Models\Sponsorship;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SpmsReportController extends Controller
{
    public function dashboard(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Sponsorship::class);

        $totalCommittedCents = (int) Sponsorship::sum('total_committed_cents');
        $totalCollectedCents = (int) Sponsorship::sum('total_paid_cents');
        $outstandingCents = max(0, $totalCommittedCents - $totalCollectedCents);
        $totalInKindCents = (int) InKindContribution::sum('estimated_value_cents');

        $activeSponsorshipsCount = Sponsorship::whereIn('status', ['active', 'approved'])->count();
        $totalOrganizationsCount = Organization::where('status', 'active')->count();
        $pendingFollowUpsCount = \App\Spms\Models\FollowUp::where('status', 'pending')->count();
        $deliverableFulfillmentRate = 0;

        $totalDeliverables = \App\Spms\Models\Deliverable::count();
        if ($totalDeliverables > 0) {
            $completedDeliverables = \App\Spms\Models\Deliverable::where('status', 'completed')->count();
            $deliverableFulfillmentRate = round(($completedDeliverables / $totalDeliverables) * 100, 1);
        }

        $recentSponsorships = Sponsorship::with(['organization', 'opportunity'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'metrics' => [
                    'total_committed_cents' => $totalCommittedCents,
                    'total_collected_cents' => $totalCollectedCents,
                    'outstanding_cents' => $outstandingCents,
                    'total_in_kind_cents' => $totalInKindCents,
                    'active_sponsorships_count' => $activeSponsorshipsCount,
                    'total_organizations_count' => $totalOrganizationsCount,
                    'pending_follow_ups_count' => $pendingFollowUpsCount,
                    'fulfillment_rate_percent' => $deliverableFulfillmentRate,
                ],
                'recent_sponsorships' => $recentSponsorships,
            ],
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        Gate::authorize('export', Sponsorship::class);

        $sponsorships = Sponsorship::with(['organization', 'opportunity', 'package', 'relationshipManager'])->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="spms_sponsorships_export_' . date('Ymd_His') . '.csv"',
        ];

        return response()->stream(function () use ($sponsorships) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Sponsorship Number',
                'Title',
                'Organization Legal Name',
                'Organization Display Name',
                'Opportunity',
                'Package',
                'Status',
                'Financial Status',
                'Fulfillment Status',
                'Committed Amount (CAD)',
                'Collected Amount (CAD)',
                'Outstanding Amount (CAD)',
                'In-Kind Estimated (CAD)',
                'Start Date',
                'End Date',
                'Created At',
            ]);

            foreach ($sponsorships as $s) {
                fputcsv($handle, [
                    $this->sanitizeCsv($s->sponsorship_number),
                    $this->sanitizeCsv($s->title),
                    $this->sanitizeCsv($s->organization?->legal_name ?? ''),
                    $this->sanitizeCsv($s->organization?->display_name ?? ''),
                    $this->sanitizeCsv($s->opportunity?->title ?? 'N/A'),
                    $this->sanitizeCsv($s->package?->name ?? 'Custom'),
                    $s->status->value,
                    $s->financial_status->value,
                    $s->fulfillment_status->value,
                    number_format($s->total_committed_cents / 100, 2, '.', ''),
                    number_format($s->total_paid_cents / 100, 2, '.', ''),
                    number_format($s->outstanding_cents / 100, 2, '.', ''),
                    number_format($s->in_kind_estimated_cents / 100, 2, '.', ''),
                    $s->start_date?->format('Y-m-d') ?? '',
                    $s->end_date?->format('Y-m-d') ?? '',
                    $s->created_at->format('Y-m-d H:i:s'),
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }

    private function sanitizeCsv(?string $value): string
    {
        if ($value === null) return '';
        $value = trim($value);
        if (in_array(substr($value, 0, 1), ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'" . $value;
        }
        return $value;
    }
}
