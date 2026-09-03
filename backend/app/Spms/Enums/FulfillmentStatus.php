<?php

namespace App\Spms\Enums;

enum FulfillmentStatus: string
{
    case NotStarted = 'not_started';
    case Scheduled = 'scheduled';
    case InProgress = 'in_progress';
    case PartiallyFulfilled = 'partially_fulfilled';
    case Fulfilled = 'fulfilled';
    case Cancelled = 'cancelled';
}
