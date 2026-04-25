<?php

namespace App\Modules\FoodListings\Requests;

use App\Modules\Core\Enums\FoodTagEnum;
use App\Modules\Core\Enums\FoodTypeEnum;
use App\Modules\Core\Requests\BaseRequest;

class UpdateFoodListingRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function store(): array
    {
        return [];
    }

    public function update(): array
    {
        return [
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'quantity' => 'sometimes|string|max:100',
            'food_type' => 'sometimes|in:'.implode(',', FoodTypeEnum::getAllValues()),
            'tags' => 'sometimes|array|min:1',
            'tags.*' => 'required|in:'.implode(',', FoodTagEnum::getAllValues()),
            'photos' => 'nullable|array',
            'photos.*' => 'string',
            'expires_at' => 'sometimes|date|after:now',
            'pickup_before' => 'sometimes|date|after:expires_at',
            'pickup_instructions' => 'nullable|string',
            'latitude' => 'sometimes|numeric|between:-90,90',
            'longitude' => 'sometimes|numeric|between:-180,180',
            'address' => 'sometimes|string|max:500',
        ];
    }
}
