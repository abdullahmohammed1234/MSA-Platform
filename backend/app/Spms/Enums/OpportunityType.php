<?php

namespace App\Spms\Enums;

enum OpportunityType: string
{
    case Event = 'event';
    case Annual = 'annual';
    case Campaign = 'campaign';
    case Program = 'program';
    case General = 'general';
    case InKind = 'in_kind';
}
