<?php

namespace App\Console\Commands;

use App\Ems\Enums\WebhookEventStatus;
use App\Ems\Models\WebhookEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Safe, dry-run-first inspection and remediation tool for unmatched Square webhook events.
 *
 * Never charges, refunds, or creates duplicate registrations.
 */
class EmsSquareUnmatchedRemediationCommand extends Command
{
    protected $signature = 'ems:square:remediate-unmatched
        {--dry-run : Inspect only without modifying any records (default)}
        {--event-id= : Target a specific Square event id}
        {--classify-external : Classify verified standalone/non-EMS POS payment as Ignored to halt retries}
        {--requeue : Requeue target record for processing}
        {--limit=50 : Maximum records to inspect}';

    protected $description = 'Inspect and remediate unmatched Square webhook events without financial side effects (defaults to dry-run)';

    public function handle(): int
    {
        $isDryRun = ! $this->option('classify-external') && ! $this->option('requeue');
        if ($this->option('dry-run')) {
            $isDryRun = true;
        }

        $eventId = $this->option('event-id');
        $limit = max(1, (int) $this->option('limit'));

        $query = WebhookEvent::query()
            ->where('provider', 'square')
            ->whereIn('status', [
                WebhookEventStatus::Unmatched->value,
                WebhookEventStatus::NeedsReview->value,
                WebhookEventStatus::Failed->value,
            ])
            ->when($eventId, fn ($q) => $q->where('event_id', $eventId))
            ->orderByDesc('id')
            ->limit($limit);

        $events = $query->get();

        if ($events->isEmpty()) {
            $this->info('No unmatched or review-pending Square webhook events found.');
            return self::SUCCESS;
        }

        $this->info(sprintf(
            '%s: Found %d unmatched/review-pending Square webhook event(s).',
            $isDryRun ? '[DRY-RUN MODE]' : '[MUTATION MODE]',
            $events->count()
        ));

        $rows = [];
        foreach ($events as $event) {
            $payload = (array) ($event->payload ?? []);
            $payment = data_get($payload, 'data.object.payment', []);
            $order = data_get($payload, 'data.object.order', []);

            $amount = data_get($payment, 'amount_money.amount', data_get($order, 'total_money.amount', 0));
            $currency = data_get($payment, 'amount_money.currency', data_get($order, 'total_money.currency', 'CAD'));
            $source = (string) data_get($payment, 'source_type', 'UNKNOWN');
            $lineItems = data_get($order, 'line_items', []);
            $lineItemNames = collect($lineItems)->pluck('name')->filter()->implode(', ') ?: 'No catalog line items';

            $rows[] = [
                'ID' => $event->id,
                'Event ID' => $event->event_id,
                'Type' => $event->event_type,
                'Status' => $event->status,
                'Attempts' => $event->retry_count,
                'Amount' => sprintf('%.2f %s', $amount / 100, $currency),
                'Source' => $source,
                'Items / Context' => mb_substr($lineItemNames, 0, 40),
                'Reason' => mb_substr((string) $event->failure_reason, 0, 45),
            ];
        }

        $this->table(['ID', 'Event ID', 'Type', 'Status', 'Attempts', 'Amount', 'Source', 'Items / Context', 'Reason'], $rows);

        if ($isDryRun) {
            $this->warn('DRY RUN: No database changes were made. Use --classify-external --event-id=<id> or --requeue --event-id=<id> to modify.');
            return self::SUCCESS;
        }

        if (! $eventId) {
            $this->error('Target --event-id is required when executing mutations to prevent accidental bulk modifications.');
            return self::FAILURE;
        }

        $target = $events->firstWhere('event_id', $eventId);
        if (! $target) {
            $this->error("Event ID '{$eventId}' not found in candidate list.");
            return self::FAILURE;
        }

        if ($this->option('classify-external')) {
            $target->status = WebhookEventStatus::Ignored->value;
            $target->next_retry_at = null;
            $target->failure_reason = 'Classified by operator as standalone non-EMS Square POS/external transaction.';
            $target->save();

            Log::channel((string) config('ems.logging.channel', 'ems'))
                ->info('ems.webhooks.square.classified_external_by_operator', [
                    'event_id' => $target->event_id,
                    'previous_status' => $target->getOriginal('status'),
                ]);

            $this->info("Successfully classified event '{$target->event_id}' as standalone external transaction. It will no longer cause retry warnings.");
        } elseif ($this->option('requeue')) {
            $target->status = WebhookEventStatus::RetryPending->value;
            $target->next_retry_at = now();
            $target->save();

            $this->info("Successfully requeued event '{$target->event_id}' for processing.");
        }

        return self::SUCCESS;
    }
}
