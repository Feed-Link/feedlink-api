<?php

namespace App\Filament\Admin\Resources\FoodListResource\Pages;

use App\Filament\Admin\Resources\FoodListResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFoodLists extends ListRecords
{
    protected static string $resource = FoodListResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
