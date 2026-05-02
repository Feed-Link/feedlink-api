<?php

namespace App\Modules\Core\Enums;

enum RolesEnum: string
{
    case DONOR = 'donor';
    case RECIPIENT = 'recipient';
    case GUEST = 'guest';

    public static function getAllValues(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function getDonorRoles(): array
    {
        return [self::DONOR->value, self::GUEST->value];
    }
}
