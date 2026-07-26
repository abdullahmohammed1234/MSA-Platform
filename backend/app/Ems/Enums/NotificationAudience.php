<?php

namespace App\Ems\Enums;

/**
 * Who should receive an organizer-triggered event update / broadcast.
 */
enum NotificationAudience: string
{
    case Everyone = 'everyone';
    case Registered = 'registered';
    case TicketHolders = 'ticket_holders';
    case None = 'none';

    public function label(): string
    {
        return match ($this) {
            self::Everyone => 'Notify everyone',
            self::Registered => 'Notify registered attendees',
            self::TicketHolders => 'Notify ticket holders',
            self::None => 'Do not notify',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
