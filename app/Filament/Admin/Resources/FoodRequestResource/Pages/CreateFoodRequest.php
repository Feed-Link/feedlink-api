<?php

namespace App\Filament\Admin\Resources\FoodRequestResource\Pages;

use App\Filament\Admin\Resources\FoodRequestResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFoodRequest extends CreateRecord
{
    protected static string $resource = FoodRequestResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
