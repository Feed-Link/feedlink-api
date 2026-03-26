<?php

namespace App\Filament\Admin\Widgets;

use App\Models\FoodRequest;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class FoodRequestStats extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Pending Requests', FoodRequest::where('status', 'pending')->count())
                ->description('Awaiting action')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),

            Stat::make('Completed Requests', FoodRequest::where('status', 'completed')->count())
                ->description('Successfully delivered')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make('Rejected Requests', FoodRequest::where('status', 'rejected')->count())
                ->description('Rejected or cancelled')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color('danger'),
        ];
    }
}
