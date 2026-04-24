<?php

namespace App\Modules\Core\Enums;

enum ClaimStatusEnum: string
{
    case PENDING = 'pending';
    case CONFIRMED = 'confirmed';
    case REJECTED = 'rejected';

    public static function getAllValues(): array
    {
        return array_column(self::cases(), 'value');
    }
}
