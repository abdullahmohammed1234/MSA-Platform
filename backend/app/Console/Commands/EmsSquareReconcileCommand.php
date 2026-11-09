<?php

namespace App\Console\Commands;

use App\Ems\Services\Square\SquareReconciliationService;
use Illuminate\Console\Command;

class EmsSquareReconcileCommand extends Command
{
    protected $signature = 'ems:square-reconcile {--since= : RFC 3339 begin time for Square payment search}';

    protected $description = 'Reconcile Square POS/online payments and refunds into EMS without duplicating tickets or refunds';

    public function handle(SquareReconciliationService $reconciliation): int
    {
        $since = $this->option('since');
        $result = $reconciliation->reconcile(is_string($since) && $since !== '' ? $since : null);

        $this->info(sprintf(
            'Reconciled Square sales: ingested=%d unmatched=%d skipped=%d errors=%d refunds_applied=%d refunds_skipped=%d refunds_errors=%d ambiguous_examined=%d ambiguous_paid=%d ambiguous_released=%d ambiguous_retained=%d',
            $result['ingested'],
            $result['unmatched'],
            $result['skipped'],
            $result['errors'],
            $result['refunds_applied'],
            $result['refunds_skipped'],
            $result['refunds_errors'],
            $result['ambiguous_examined'] ?? 0,
            $result['ambiguous_reconciled_paid'] ?? 0,
            $result['ambiguous_inventory_released'] ?? 0,
            $result['ambiguous_uncertain_retained'] ?? 0
        ));

        return self::SUCCESS;
    }
}
