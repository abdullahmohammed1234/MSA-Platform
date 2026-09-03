<?php

namespace App\Spms\Enums;

enum OrganizationStatus: string
{
    case Prospect = 'prospect';
    case Active = 'active';
    case Inactive = 'inactive';
    case Archived = 'archived';
}
