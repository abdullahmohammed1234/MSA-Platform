<?php

namespace App\Console\Commands;

use App\Ems\Services\Square\SquareReconciliationService;
use Illuminate\Console\Command;

class EmsSquareReconcileCommand extends Command
{
    protected $signature = 'ems:square-reconcile {--since= : RFC 3339 begin time for Square payment search}';

    protected $description = 'Reconcile Square POS/online payments into EMS without duplicating tickets';

    public function handle(SquareReconciliationService $reconciliation): int
    {
        $since = $this->option('since');
        $result = $reconciliation->reconcile(is_string($since) && $since !== '' ? $since : null);

        $this->info(sprintf(
            'Reconciled Square sales: ingested=%d unmatched=%d skipped=%d errors=%d',
            $result['ingested'],
            $result['unmatched'],
            $result['skipped'],
            $result['errors']
        ));

        return self::SUCCESS;
    }
}
