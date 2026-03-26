<?php

namespace App\Filament\Admin\Widgets;

use App\Models\FoodList;
use App\Models\FoodRequest;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class StatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;
    
    protected int | string | array $columnSpan = 'full';

    protected function getStats(): array
    {
        return [
            Stat::make('Total Users', Cache::remember('total_users', 3600, fn() => User::count()))
                ->description('All registered users')
                ->descriptionIcon('heroicon-m-users')
                ->color('info'),

            Stat::make('Total Food Listings', Cache::remember('total_food_listings', 3600, fn() => FoodList::count()))
                ->description('Available food items')
                ->descriptionIcon('heroicon-m-list-bullet')
                ->color('success'),

            Stat::make('Food Requests', Cache::remember('total_food_requests', 3600, fn() => FoodRequest::count()))
                ->description('All received requests')
                ->descriptionIcon('heroicon-m-inbox-stack')
                ->color('warning'),

            Stat::make('Verified Users', Cache::remember('verified_users', 3600, fn() => User::whereNotNull('email_verified_at')->count()))
                ->description('Email verified accounts')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),
        ];
    }
}
