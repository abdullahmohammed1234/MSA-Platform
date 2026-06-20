<?php

namespace App\Jobs\Reports;

use App\Jobs\Analytics\GenerateScheduledReportsJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateWeeklyReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        $this->queue = 'low';
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $job = new GenerateScheduledReportsJob('weekly');
        $job->handle();
    }
}
