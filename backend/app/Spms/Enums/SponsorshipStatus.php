<?php

namespace App\Spms\Enums;

enum SponsorshipStatus: string
{
    case Prospect = 'prospect';
    case Proposed = 'proposed';
    case Negotiating = 'negotiating';
    case Approved = 'approved';
    case Active = 'active';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
}
