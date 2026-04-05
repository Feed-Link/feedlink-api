<?php

namespace App\Modules\FoodListings\Requests;

use App\Modules\Core\Requests\BaseRequest;
use App\Modules\Core\Enums\FoodTagEnum;

class StoreFoodListingRequest extends BaseRequest
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
            'quantity' => 'required|string|max:100',
            'tags' => 'required|array|min:1',
            'tags.*' => 'required|in:' . implode(',', FoodTagEnum::getAllValues()),
            'photos' => 'nullable|array',
            'photos.*' => 'string',
            'expires_at' => 'required|date|after:now',
            'pickup_before' => 'required|date|after:expires_at',
            'pickup_instructions' => 'nullable|string',
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
            'quantity.required' => 'The quantity field is required.',
            'tags.required' => 'At least one tag is required.',
            'tags.min' => 'At least one tag is required.',
            'tags.*.in' => 'One or more selected tags are invalid.',
            'expires_at.required' => 'The expiration date is required.',
            'expires_at.after' => 'The expiration date must be in the future.',
            'pickup_before.required' => 'The pickup deadline is required.',
            'pickup_before.after' => 'Pickup deadline must be after expiration date.',
            'latitude.required' => 'Latitude is required.',
            'longitude.required' => 'Longitude is required.',
            'address.required' => 'The address is required.',
        ];
    }
}
