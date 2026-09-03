<?php

namespace App\Spms\Enums;

enum FinancialStatus: string
{
    case Uncommitted = 'uncommitted';
    case Committed = 'committed';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Overdue = 'overdue';
    case Refunded = 'refunded';
    case Cancelled = 'cancelled';
}
