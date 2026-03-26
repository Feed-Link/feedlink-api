<?php

namespace App\Filament\Admin\Resources\FoodListResource\Pages;

use App\Filament\Admin\Resources\FoodListResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFoodList extends EditRecord
{
    protected static string $resource = FoodListResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
