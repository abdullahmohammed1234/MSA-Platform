<?php

namespace App\Spms\Enums;

enum DeliverableType: string
{
    case LogoPlacement = 'logo_placement';
    case SocialPost = 'social_post';
    case EventBooth = 'event_booth';
    case SpeakingSlot = 'speaking_slot';
    case PromoMention = 'promo_mention';
    case Tickets = 'tickets';
    case Banner = 'banner';
    case Newsletter = 'newsletter';
    case Other = 'other';
}
