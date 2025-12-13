<?php

namespace App\Modules\FoodShare\Enums;

enum FoodRequestEnums: string
{
    case PENDING = 'pending';
    case APPROVE = 'approve';
    case COMPLETED = 'completed';
    case REJECTED = 'rejected';

    public static function getAllValues(): array
    {
        return array_column(self::cases(), 'value');
    }
}
