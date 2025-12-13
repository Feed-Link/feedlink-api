<?php

namespace App\Modules\FoodShare\Enums;

enum FoodListTypeEnums: string
{
    case DONATE = 'donate';
    case REQUEST = 'request';

    public static function getAllValues(): array
    {
        return array_column(self::cases(), 'value');
    }
}
