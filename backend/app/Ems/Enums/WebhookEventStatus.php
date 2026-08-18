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

    public function canReprocess(): bool
    {
        return in_array($this, [self::Unmatched, self::Failed, self::RetryPending, self::Received], true);
    }
}
