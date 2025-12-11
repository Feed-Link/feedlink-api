<?php

namespace App\Modules\Core\Enums;

enum RolesEnum: string
{
    case DONOR = 'donor';
    case RECIPIENT = 'recipient';

    public static function getAllValues(): array
    {
        return array_column(self::cases(), "value");
    }
}
