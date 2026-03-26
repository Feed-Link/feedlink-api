<?php

namespace App\Filament\Admin\Resources\FoodRequestResource\Pages;

use App\Filament\Admin\Resources\FoodRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFoodRequest extends EditRecord
{
    protected static string $resource = FoodRequestResource::class;

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
