<?php

namespace App\Console\Commands;

use App\Ems\Enums\WebhookEventStatus;
use App\Ems\Models\WebhookEvent;
use App\Ems\Services\SquareWebhookService;
use Illuminate\Console\Command;

class EmsSquareWebhookRequeueCommand extends Command
{
    protected $signature = 'ems:square-webhooks:requeue
        {event_ids?* : Square event ids to requeue}
        {--status= : Requeue every row in this status (needs_review, failed, unmatched, ignored)}
        {--limit=50 : Maximum rows when using --status}
        {--list : Only list matching rows}';

    protected $description = 'List or intentionally requeue Square webhook events that automatic retries will not resume';

    public function handle(SquareWebhookService $webhooks): int
    {
        $ids = (array) $this->argument('event_ids');
        $status = $this->option('status');

        if ($ids === [] && ! is_string($status)) {
            $status = WebhookEventStatus::NeedsReview->value;
            $this->option('list') || $this->error('Pass event ids or --status to requeue. Listing needs_review rows instead.');
            $listOnly = true;
        }

        if (is_string($status) && ! in_array($status, WebhookEventStatus::operatorRequeueable(), true)) {
            $this->error('Status must be one of: '.implode(', ', WebhookEventStatus::operatorRequeueable()));

            return self::INVALID;
        }

        $rows = WebhookEvent::query()
            ->where('provider', 'square')
            ->when($ids !== [], fn ($q) => $q->whereIn('event_id', $ids))
            ->when($ids === [] && is_string($status), fn ($q) => $q->where('status', $status))
            ->orderBy('id')
            ->limit(max(1, (int) $this->option('limit')))
            ->get(['id', 'uuid', 'event_id', 'event_type', 'status', 'retry_count', 'failure_reason', 'updated_at']);

        $this->table(
            ['event_id', 'type', 'status', 'attempts', 'reason', 'updated'],
            $rows->map(fn (WebhookEvent $r) => [$r->event_id, $r->event_type, $r->status, $r->retry_count, mb_substr((string) $r->failure_reason, 0, 80), (string) $r->updated_at])->all(),
        );

        if ($this->option('list') || ($listOnly ?? false)) {
            return self::SUCCESS;
        }

        $requeued = 0;
        foreach ($rows as $row) {
            if ($webhooks->requeueForOperator($row, null, 'artisan')) {
                $requeued++;
            } else {
                $this->warn("Skipped {$row->event_id}: status {$row->status} is not requeueable.");
            }
        }

        $this->info("Requeued {$requeued} webhook event(s).");

        return self::SUCCESS;
    }
}
