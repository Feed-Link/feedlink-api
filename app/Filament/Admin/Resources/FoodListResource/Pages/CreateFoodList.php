<?php

namespace App\Filament\Admin\Resources\FoodListResource\Pages;

use App\Filament\Admin\Resources\FoodListResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFoodList extends CreateRecord
{
    protected static string $resource = FoodListResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
