<?php

namespace App\Ems\Jobs;

use App\Ems\Services\WaitlistService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ExpireStalePromotionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        $this->onQueue((string) config('ems.operations.queue', 'ems-operations'));
        $this->afterCommit = true;
    }

    public function handle(WaitlistService $waitlist): void
    {
        $waitlist->expireStale();
    }
}
