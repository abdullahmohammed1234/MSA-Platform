<?php

namespace App\Ems\Enums;

enum ReminderAudience: string
{
    case All = 'all';
    case Confirmed = 'confirmed';
    case TicketHolders = 'ticket_holders';

    public function label(): string
    {
        return match ($this) {
            self::All => 'All registrants',
            self::Confirmed => 'Confirmed attendees',
            self::TicketHolders => 'Ticket holders',
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
