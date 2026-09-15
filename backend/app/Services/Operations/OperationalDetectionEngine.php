<?php

namespace App\Services\Operations;

use App\Services\Operations\Rules\CommunicationOperationalRules;
use App\Services\Operations\Rules\DonationOperationalRules;
use App\Services\Operations\Rules\EmsOperationalRules;
use App\Services\Operations\Rules\MlibmsOperationalRules;
use App\Services\Operations\Rules\PlatformOperationalRules;
use App\Services\Operations\Rules\StoreOperationalRules;
use App\Services\Operations\Rules\VolunteerOperationalRules;
use Illuminate\Support\Facades\Log;
use Throwable;

class OperationalDetectionEngine
{
    public function __construct(
        private EmsOperationalRules $emsRules,
        private CommunicationOperationalRules $commsRules,
        private MlibmsOperationalRules $mlibmsRules,
        private StoreOperationalRules $storeRules,
        private PlatformOperationalRules $platformRules,
        private VolunteerOperationalRules $volunteerRules,
        private DonationOperationalRules $donationRules
    ) {}

    /**
     * Run all registered operational detection rules with fault isolation.
     *
     * @return array{total_detected: int, details: array<string, int>, errors: array<string, string>}
     */
    public function runAll(): array
    {
        $rules = [
            'ems' => $this->emsRules,
            'communications' => $this->commsRules,
            'mlibms' => $this->mlibmsRules,
            'store' => $this->storeRules,
            'platform' => $this->platformRules,
            'volunteering' => $this->volunteerRules,
            'donations' => $this->donationRules,
        ];

        $details = [];
        $errors = [];
        $totalDetected = 0;

        foreach ($rules as $category => $ruleModule) {
            try {
                $count = $ruleModule->detect();
                $details[$category] = $count;
                $totalDetected += $count;
            } catch (Throwable $e) {
                $details[$category] = 0;
                $errors[$category] = $e->getMessage();
                Log::error("Operational detection rule failure [{$category}]: " . $e->getMessage(), [
                    'category' => $category,
                    'exception' => $e,
                ]);
            }
        }

        return [
            'total_detected' => $totalDetected,
            'details' => $details,
            'errors' => $errors,
        ];
    }
}
