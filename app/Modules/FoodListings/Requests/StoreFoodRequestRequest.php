<?php

namespace App\Modules\FoodListings\Requests;

use App\Modules\Core\Enums\FoodTagEnum;
use App\Modules\Core\Enums\FoodTypeEnum;
use App\Modules\Core\Requests\BaseRequest;

class StoreFoodRequestRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function store(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'quantity_needed' => 'required|string|max:100',
            'food_type' => 'required|in:'.implode(',', FoodTypeEnum::getAllValues()),
            'tags' => 'required|array|min:1',
            'tags.*' => 'required|in:'.implode(',', FoodTagEnum::getAllValues()),
            'needed_by' => 'required|date|after:now',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'address' => 'required|string|max:500',
        ];
    }

    public function update(): array
    {
        return [];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'The title field is required.',
            'quantity_needed.required' => 'The quantity needed is required.',
            'tags.required' => 'At least one tag is required.',
            'tags.min' => 'At least one tag is required.',
            'tags.*.in' => 'One or more selected tags are invalid.',
            'needed_by.required' => 'The needed by date is required.',
            'needed_by.after' => 'The needed by date must be in the future.',
            'latitude.required' => 'Latitude is required.',
            'longitude.required' => 'Longitude is required.',
            'address.required' => 'The address is required.',
        ];
    }
}
