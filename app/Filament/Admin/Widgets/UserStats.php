<?php

namespace App\Filament\Admin\Widgets;

use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class UserStats extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Verified Users', User::whereNotNull('email_verified_at')->count())
                ->description('Users with verified emails')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('success'),

            Stat::make('Unverified Users', User::whereNull('email_verified_at')->count())
                ->description('Pending email verification')
                ->descriptionIcon('heroicon-m-exclamation-circle')
                ->color('warning'),
        ];
    }
}
