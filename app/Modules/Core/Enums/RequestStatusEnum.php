<?php

namespace App\Modules\Core\Enums;

enum RequestStatusEnum: string
{
    case OPEN = 'open';
    case ACCEPTED = 'accepted';
    case FULFILLED = 'fulfilled';
    case EXPIRED = 'expired';
    case CANCELLED = 'cancelled';

    public static function getAllValues(): array
    {
        return array_column(self::cases(), 'value');
    }
}
