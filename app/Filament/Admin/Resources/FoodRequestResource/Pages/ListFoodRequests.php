<?php

namespace App\Filament\Admin\Resources\FoodRequestResource\Pages;

use App\Filament\Admin\Resources\FoodRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFoodRequests extends ListRecords
{
    protected static string $resource = FoodRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
