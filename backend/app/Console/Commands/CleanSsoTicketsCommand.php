<?php

namespace App\Console\Commands;

use App\Models\SsoTicket;
use Illuminate\Console\Command;

class CleanSsoTicketsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sso:clean-tickets
                            {--hours=24 : Delete consumed tickets older than specified hours}
                            {--expired-only : Only delete tickets that have expired}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prune expired and consumed single-use SSO exchange tickets.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        $expiredOnly = (bool) $this->option('expired-only');

        // 1. Delete all expired tickets (expired tickets can never be consumed)
        $expiredDeleted = SsoTicket::where('expires_at', '<', now())->delete();

        // 2. Delete consumed tickets older than retention window
        $consumedDeleted = 0;
        if (!$expiredOnly) {
            $threshold = now()->subHours($hours);
            $consumedDeleted = SsoTicket::whereNotNull('consumed_at')
                ->where('consumed_at', '<', $threshold)
                ->delete();
        }

        $total = $expiredDeleted + $consumedDeleted;

        $this->info("Pruned {$total} SSO tickets ({$expiredDeleted} expired, {$consumedDeleted} consumed).");

        return self::SUCCESS;
    }
}

