<?php

namespace App\Modules\Core\Enums;

enum FoodTypeEnum: string
{
    case HUMAN = 'human';
    case ANIMAL = 'animal';
    case BOTH = 'both';

    public static function getAllValues(): array
    {
        return array_column(self::cases(), 'value');
    }
}
