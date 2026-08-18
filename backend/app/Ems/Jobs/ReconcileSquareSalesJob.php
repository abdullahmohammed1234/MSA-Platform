<?php

namespace App\Ems\Jobs;

use App\Ems\Services\Square\SquareReconciliationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ReconcileSquareSalesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly ?string $since = null)
    {
        $this->onQueue((string) config('ems.payments.queue', 'ems-payments'));
    }

    public function handle(SquareReconciliationService $reconciliation): void
    {
        $reconciliation->reconcile($this->since);
    }
}
