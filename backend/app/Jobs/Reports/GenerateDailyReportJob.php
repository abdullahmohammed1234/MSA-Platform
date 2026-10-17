<?php

namespace App\Jobs\Reports;

use App\Jobs\Analytics\GenerateScheduledReportsJob;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateDailyReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // A retry after the report row is created would duplicate it and re-email admins.
    public $tries = 1;

    // Captured when the scheduler dispatches the job, not when the worker runs it.
    protected ?string $referenceDate = null;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        $this->queue = 'low';
        $this->referenceDate = Carbon::today()->toDateString();
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $job = new GenerateScheduledReportsJob('daily', $this->referenceDate);
        $job->handle();
    }
}
