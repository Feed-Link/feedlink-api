<?php

namespace App\Modules\Core\Enums;

enum FoodTagCategoryEnum: string
{
    case AUDIENCE = 'audience';
    case STATE = 'state';

    public static function getAllValues(): array
    {
        return array_column(self::cases(), 'value');
    }
}
