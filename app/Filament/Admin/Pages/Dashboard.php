<?php

namespace App\Filament\Admin\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?int $navigationSort = 0;

    public function getWidgets(): array
    {
        return [
            \App\Filament\Admin\Widgets\StatsOverview::class,
            \App\Filament\Admin\Widgets\UserStats::class,
            \App\Filament\Admin\Widgets\FoodListingStats::class,
            \App\Filament\Admin\Widgets\FoodRequestStats::class,
        ];
    }

    public function getColumns(): array|int
    {
        return [
            'default' => 1,
            'sm' => 2,
            'md' => 4,
            'lg' => 4,
        ];
    }
}
