<?php

namespace App\Modules\Core\Enums;

enum FoodTagEnum: string
{
    // Audience
    case FOR_HUMANS = 'for_humans';
    case FOR_ANIMALS = 'for_animals';
    case FOR_BOTH = 'for_both';

    // Food state
    case COOKED = 'cooked';
    case RAW_INGREDIENTS = 'raw_ingredients';
    case PACKAGED = 'packaged';

    public static function getAllValues(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function getByCategory(string $category): array
    {
        return match ($category) {
            'audience' => [
                self::FOR_HUMANS->value,
                self::FOR_ANIMALS->value,
                self::FOR_BOTH->value,
            ],
            'state' => [
                self::COOKED->value,
                self::RAW_INGREDIENTS->value,
                self::PACKAGED->value,
            ],
            default => [],
        };
    }
}
