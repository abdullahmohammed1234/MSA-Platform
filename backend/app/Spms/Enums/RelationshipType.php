<?php

namespace App\Spms\Enums;

enum RelationshipType: string
{
    case Sponsor = 'sponsor';
    case Partner = 'partner';
    case InKindPartner = 'in_kind_partner';
    case CommunityPartner = 'community_partner';
    case MediaPartner = 'media_partner';
    case VendorPartner = 'vendor_partner';
    case StrategicPartner = 'strategic_partner';
}
