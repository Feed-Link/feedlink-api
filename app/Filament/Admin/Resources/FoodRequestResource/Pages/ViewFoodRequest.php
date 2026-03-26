<?php

namespace App\Filament\Admin\Resources\FoodRequestResource\Pages;

use App\Filament\Admin\Resources\FoodRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewFoodRequest extends ViewRecord
{
    protected static string $resource = FoodRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
