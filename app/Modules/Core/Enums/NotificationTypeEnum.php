<?php

namespace App\Modules\Core\Enums;

enum NotificationTypeEnum: string
{
    case CLAIM_RECEIVED = 'claim_received';

    public static function getAllValues(): array
    {
        return array_column(self::cases(), 'value');
    }
}
