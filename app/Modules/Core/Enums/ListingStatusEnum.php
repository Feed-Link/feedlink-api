<?php

namespace App\Modules\Core\Enums;

enum ListingStatusEnum: string
{
    case ACTIVE = 'active';
    case CLAIMED = 'claimed';
    case COMPLETED = 'completed';
    case EXPIRED = 'expired';
    case CANCELLED = 'cancelled';

    public static function getAllValues(): array
    {
        return array_column(self::cases(), 'value');
    }
}
