<?php

namespace App\Modules\Core\Enums;

enum NotificationTypeEnum: string
{
    case CLAIM_RECEIVED = 'claim_received';
    case CLAIM_CONFIRMED = 'claim_confirmed';
    case CLAIM_REJECTED = 'claim_rejected';
    case PICKUP_COMPLETED = 'pickup_completed';

    public static function getAllValues(): array
    {
        return array_column(self::cases(), 'value');
    }
}
