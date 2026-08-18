<?php

namespace App\Ems\Jobs;

use App\Ems\Services\CheckoutLifecycleService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ExpireAbandonedCheckoutsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        $this->onQueue((string) config('ems.payments.queue', 'ems-payments'));
    }

    public function handle(CheckoutLifecycleService $lifecycle): void
    {
        $lifecycle->expireStale();
    }
}
