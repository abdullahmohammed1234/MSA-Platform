<?php

namespace App\Jobs;

use App\Models\CertificateAward;
use App\Services\CertificateGenerationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateCertificatePdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $award;

    /**
     * Create a new job instance.
     */
    public function __construct(CertificateAward $award)
    {
        $this->award = $award;
    }

    /**
     * Execute the job.
     */
    public function handle(CertificateGenerationService $generator): void
    {
        $generator->generatePdf($this->award);
    }
}
