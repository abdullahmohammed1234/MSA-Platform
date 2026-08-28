<?php

namespace App\Ems\Jobs;

use App\Ems\Models\Payment;
use App\Ems\Services\PaymentReconciliationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ReconcilePaymentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $paymentId)
    {
        $this->afterCommit = true;
        $this->onQueue((string) config('ems.payments.queue', 'ems-payments'));
    }

    public function handle(PaymentReconciliationService $reconciliation): void
    {
        $payment = Payment::query()->find($this->paymentId);

        if ($payment === null) {
            return;
        }

        $reconciliation->reconcile($payment);
    }
}
