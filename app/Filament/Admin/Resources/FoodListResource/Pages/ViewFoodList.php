<?php

namespace App\Filament\Admin\Resources\FoodListResource\Pages;

use App\Filament\Admin\Resources\FoodListResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewFoodList extends ViewRecord
{
    protected static string $resource = FoodListResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
