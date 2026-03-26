<?php

namespace App\Filament\Admin\Widgets;

use App\Models\FoodList;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class FoodListingStats extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Total Listings', FoodList::count())
                ->description('All food listings')
                ->descriptionIcon('heroicon-m-server-stack')
                ->color('info'),

            Stat::make('Active Listings (30 days)', FoodList::where('created_at', '>=', now()->subDays(30))->count())
                ->description('Posted in last 30 days')
                ->descriptionIcon('heroicon-m-calendar')
                ->color('success'),

            Stat::make('Unique Food Types', FoodList::distinct('type')->count())
                ->description('Different types available')
                ->descriptionIcon('heroicon-m-tag')
                ->color('warning'),
        ];
    }
}
