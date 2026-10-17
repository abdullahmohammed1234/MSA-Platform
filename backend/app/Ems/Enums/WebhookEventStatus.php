<?php

namespace App\Ems\Enums;

enum WebhookEventStatus: string
{
    case Received = 'received';
    case Processing = 'processing';
    case Processed = 'processed';
    case Unmatched = 'unmatched';
    case Failed = 'failed';
    case RetryPending = 'retry_pending';
    /** Automatic retries exhausted or outcome ambiguous; only an operator requeue resumes it. */
    case NeedsReview = 'needs_review';
    /** Event type EMS does not handle; kept for audit, never retried automatically. */
    case Ignored = 'ignored';

    public function canReprocess(): bool
    {
        return in_array($this, [self::Unmatched, self::Failed, self::RetryPending, self::Received, self::NeedsReview, self::Ignored], true);
    }

    /**
     * @return list<string>
     */
    public static function operatorRequeueable(): array
    {
        return [self::NeedsReview->value, self::Failed->value, self::Unmatched->value, self::Ignored->value];
    }
}
